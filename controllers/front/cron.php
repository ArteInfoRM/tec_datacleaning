<?php

use PrestaShop\PrestaShop\Core\Module\ModuleManagerBuilder;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_datacleanincdgCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Read token from request (we expect 'secure_key' in the URL)
        $token = Tools::getValue('secure_key');

        // Compute deterministic token as used in other modules
        $moduleInstance = null;
        if (class_exists('Module')) {
            $moduleInstance = Module::getInstanceByName('tec_datacleaning');
        }
        $moduleName = ($moduleInstance && !empty($moduleInstance->name)) ? $moduleInstance->name : 'tec_datacleaning';

        $expectedToken = '';
        if (defined('_COOKIE_KEY_')) {
            $expectedToken = md5(_COOKIE_KEY_ . $moduleName);
        } elseif (class_exists('Tools') && method_exists('Tools', 'encrypt')) {
            $expectedToken = Tools::encrypt($moduleName);
        }

        if (empty($token) || $token !== $expectedToken) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => false, 'message' => 'Invalid token'));
            exit;
        }

        // Params
        $dryRun = (bool)Tools::getValue('dry_run');
        $batchSize = (int)Tools::getValue('batch_size');
        if ($batchSize <= 0) {
            $batchSize = (int)Configuration::get('TEC_DATACLEANIG_BATCH_SIZE');
            if ($batchSize < 1) {
                $batchSize = 1000;
            }
        }

        $months = (int)Configuration::get('TEC_DATACLEANIG_MONTHS');
        if ($months < 1) {
            $months = 1;
        }

        // Get selected tables
        $cfg = Configuration::get('TEC_DATACLEANIG_SELECTED_TABLES');
        $selected = array();
        if ($cfg) {
            $un = @unserialize($cfg, ['allowed_classes' => false]);
            if (is_array($un)) {
                $selected = $un;
            }
        }

        // If truncate flag is present, truncate each selected table (fast cleanup), excluding ps_log
        $truncateFlag = (bool)Tools::getValue('truncate');
        if ($truncateFlag) {
            $db = Db::getInstance();
            $truncateResults = array();

            // Get allowed tables from module if possible for safety
            $allowedTables = array();
            if (isset($this->module) && is_object($this->module) && method_exists($this->module, 'getAllowedTables')) {
                $allowedTables = $this->module->getAllowedTables();
            } elseif (class_exists('Module')) {
                $mod = Module::getInstanceByName('tec_datacleaning');
                if ($mod && method_exists($mod, 'getAllowedTables')) {
                    $allowedTables = $mod->getAllowedTables();
                }
            }

            // If dry run is requested, only count rows that would be removed
            if ($dryRun) {
                foreach ($selected as $tbl) {
                    // Always skip the log table
                    if ($tbl === _DB_PREFIX_ . 'log') {
                        $truncateResults[$tbl] = array('status' => 'skipped_log_table');
                        continue;
                    }

                    // Ensure table is allowed (safety)
                    if (!empty($allowedTables) && !in_array($tbl, $allowedTables)) {
                        $truncateResults[$tbl] = array('status' => 'not_allowed');
                        continue;
                    }

                    // Verify table exists
                    $tblCheck = $db->executeS("SHOW TABLES LIKE '" . pSQL($tbl) . "'");
                    if (empty($tblCheck)) {
                        $truncateResults[$tbl] = array('status' => 'missing');
                        continue;
                    }

                    // Count rows that would be truncated
                    try {
                        $count = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                        $truncateResults[$tbl] = array('status' => 'would_truncate', 'rows' => $count);
                    } catch (Exception $e) {
                        $truncateResults[$tbl] = array('status' => 'error', 'error' => $e->getMessage());
                    }
                }

                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array('success' => true, 'action' => 'truncate', 'dry_run' => true, 'results' => $truncateResults));
                exit;
            }

            // Not a dry run: perform actual TRUNCATE for allowed tables
            foreach ($selected as $tbl) {
                // Always skip the log table
                if ($tbl === _DB_PREFIX_ . 'log') {
                    $truncateResults[$tbl] = 'skipped_log_table';
                    continue;
                }

                // Ensure table is allowed (safety)
                if (!empty($allowedTables) && !in_array($tbl, $allowedTables)) {
                    $truncateResults[$tbl] = 'not_allowed';
                    continue;
                }

                // Verify table exists
                $tblCheck = $db->executeS("SHOW TABLES LIKE '" . pSQL($tbl) . "'");
                if (empty($tblCheck)) {
                    $truncateResults[$tbl] = 'missing';
                    continue;
                }

                // Perform TRUNCATE with fallback to batched DELETE if TRUNCATE is not allowed
                try {
                    $ok = $db->execute('TRUNCATE TABLE `' . bqSQL($tbl) . '`');
                    if ($ok) {
                        $truncateResults[$tbl] = 'ok';
                    } else {
                        // TRUNCATE returned false (no exception) - attempt fallback
                        $initialCount = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                        $deletedTotal = 0;
                        if ($initialCount > 0) {
                            // Safety: compute max iterations to avoid infinite loops
                            $maxIterations = (int)ceil($initialCount / max(1, $batchSize)) + 5;
                            $iter = 0;
                            while ($iter < $maxIterations) {
                                $before = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                                if ($before <= 0) {
                                    break;
                                }
                                $res = $db->execute('DELETE FROM `' . bqSQL($tbl) . '` LIMIT ' . (int)$batchSize);
                                // Recalculate after
                                $after = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                                $deletedNow = max(0, $before - $after);
                                $deletedTotal += $deletedNow;
                                // If no progress, break
                                if ($deletedNow === 0) {
                                    break;
                                }
                                $iter++;
                            }
                        }
                        if ($deletedTotal > 0) {
                            $truncateResults[$tbl] = array('truncate' => 'failed', 'fallback' => 'deleted_in_batches', 'deleted' => $deletedTotal);
                        } else {
                            $truncateResults[$tbl] = array('truncate' => 'failed', 'fallback' => 'no_rows_deleted');
                        }
                    }
                } catch (Exception $e) {
                    // TRUNCATE threw an exception (likely permissions or FK). Try fallback batched DELETE.
                    $fallbackResult = array('truncate' => 'error', 'error' => $e->getMessage());
                    try {
                        $initialCount = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                        $deletedTotal = 0;
                        if ($initialCount > 0) {
                            $maxIterations = (int)ceil($initialCount / max(1, $batchSize)) + 5;
                            $iter = 0;
                            while ($iter < $maxIterations) {
                                $before = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                                if ($before <= 0) {
                                    break;
                                }
                                $res = $db->execute('DELETE FROM `' . bqSQL($tbl) . '` LIMIT ' . (int)$batchSize);
                                $after = (int)$db->getValue('SELECT COUNT(*) FROM `' . bqSQL($tbl) . '`');
                                $deletedNow = max(0, $before - $after);
                                $deletedTotal += $deletedNow;
                                if ($deletedNow === 0) {
                                    break;
                                }
                                $iter++;
                            }
                        }
                        if ($deletedTotal > 0) {
                            $fallbackResult['fallback'] = 'deleted_in_batches';
                            $fallbackResult['deleted'] = $deletedTotal;
                        } else {
                            $fallbackResult['fallback'] = 'no_rows_deleted';
                        }
                    } catch (Exception $e2) {
                        $fallbackResult['fallback_error'] = $e2->getMessage();
                    }
                    $truncateResults[$tbl] = $fallbackResult;
                }
            }

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => true, 'action' => 'truncate', 'dry_run' => false, 'results' => $truncateResults));
            exit;
        }

        // Use module instance if available to call cleaning methods
        $moduleCaller = $moduleInstance ? $moduleInstance : (isset($this->module) ? $this->module : null);

        $results = array();
        foreach ($selected as $tbl) {
            if ($moduleCaller && method_exists($moduleCaller, 'cleanOldData')) {
                $res = $moduleCaller->cleanOldData($months, $tbl, array('dry_run' => $dryRun, 'batch_size' => $batchSize));
            } else {
                $res = array('success' => false, 'message' => 'Module instance not available');
            }
            $results[$tbl] = $res;
        }

        // Run orphan cleanup
        if ($moduleCaller && method_exists($moduleCaller, 'cleanOrphanedData')) {
            $orphanRes = $moduleCaller->cleanOrphanedData();
        } else {
            $orphanRes = array('success' => false, 'message' => 'Module instance not available');
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => true, 'dry_run' => $dryRun, 'batch_size' => $batchSize, 'months' => $months, 'tables' => $results, 'orphan' => $orphanRes));
        exit;
    }
}
