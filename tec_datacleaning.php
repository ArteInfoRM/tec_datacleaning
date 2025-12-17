<?php
/**
 *  2009-2025 Tecnoacquisti.com
 *
 *  For support feel free to contact us on our website at http://www.tecnoacquisti.com
 *
 *  @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 *  @copyright 2009-2025 Arte e Informatica
 *  @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 *  @version   1.0.3
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_datacleaning extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'tec_datacleaning';
        $this->tab = 'quick_bulk_update';
        $this->version = '1.0.5';
        $this->author = 'Tecnoacquisti.com';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Database Stats Cleaning');
        $this->description = $this->l('With this Prestashop module, you can optimize your database and enhance your shop\'s performance. The cleaned tables in the database can often be quite large, especially if your store receives a high volume of visits.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        // Call parent install and register required hooks first
        if (!parent::install() || !$this->registerHook('displayBackOfficeHeader')) {
            return false;
        }

        // Preconfigure default configuration values
        Configuration::updateValue('TEC_DATACLEANIG_MONTHS', 1);
        Configuration::updateValue('TEC_DATACLEANIG_BATCH_SIZE', 1000);
        // Default selected tables: use the allowed tables list
        $defaultTables = $this->getAllowedTables();
        if (!empty($defaultTables)) {
            // Use JSON for safe persistence instead of serialize()
            Configuration::updateValue('TEC_DATACLEANIG_SELECTED_TABLES', json_encode(array_values($defaultTables)));
        }

        // Ensure a persistent secure key exists for cron usage
        // Persist secure key using the module helper but only if there isn't one yet
        if (!Configuration::get('TEC_DATACLEANIG_SECURE_KEY')) {
            $computed = $this->computeModuleSecureKey();
            if ($computed !== 'NOKEY') {
                Configuration::updateValue('TEC_DATACLEANIG_SECURE_KEY', $computed);
            }
        }
        return true;

    }

    public function uninstall()
    {
        Configuration::deleteByName('TEC_DATACLEANIG_MONTHS');
        Configuration::deleteByName('TEC_DATACLEANIG_BATCH_SIZE');
        Configuration::deleteByName('TEC_DATACLEANIG_SELECTED_TABLES');
        Configuration::deleteByName('TEC_DATACLEANIG_SECURE_KEY');
        return parent::uninstall();
    }

    /**
     * Hook displayBackOfficeHeader required by PrestaShop when registered
     * Return empty string to avoid output in BO header
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        return '';
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {

        $output = null;
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitTec_datacleanigModule')) == true) {
            // Collect form errors and show them together; avoid showing Success when there are errors
            $formErrors = array();
             $monthsToKeep = (int)Tools::getValue('TEC_DATACLEANIG_MONTHS');
             // Read selected tables robustly: handle HelperForm checkbox naming and array POST
             $selectedTables = array();
             // 1) If HelperForm posted array directly
             $postedArray = Tools::getValue('TEC_DATACLEANIG_SELECTED_TABLES');
             if (is_array($postedArray)) {
                 // Two possible shapes:
                 // a) associative array: ['ps_connections' => 1, 'ps_log' => 1]
                 // b) sequential array: ['ps_connections', 'ps_log']
                 $allValuesNumeric = true;
                 foreach ($postedArray as $k => $v) {
                     if (!is_numeric($v)) {
                         $allValuesNumeric = false;
                         break;
                     }
                 }
                 if ($allValuesNumeric && count($postedArray) > 0) {
                     // associative or numeric values -> use keys as table names
                     $selectedTables = array_keys(array_filter($postedArray));
                 } else {
                     // sequential list of table names
                     $selectedTables = array_values($postedArray);
                 }
             }

             // Save custom secure key if provided by admin in the form
             // Read posted secure key but do NOT persist yet. Persist only if all validations pass.
             $newPostedKey = null;
             $postedKey = Tools::getValue('TEC_DATACLEANIG_SECURE_KEY');
             if (is_string($postedKey)) {
                 $postedKey = trim($postedKey);
                 // Reject empty or sentinel 'NOKEY' values (case-insensitive)
                 if ($postedKey === '' || strcasecmp($postedKey, 'NOKEY') === 0) {
                     $formErrors[] = $this->l('Secure key is not valid. Please provide a valid key.');
                 } else {
                     // keep value to persist later
                     $newPostedKey = $postedKey;
                 }
             }

             // 2) Scan raw POST for checkbox-style keys generated by HelperForm: TEC_DATACLEANIG_SELECTED_TABLES_table_name or TEC_DATACLEANIG_SELECTED_TABLES[table_name]
             foreach ($_POST as $k => $v) {
                 if (strpos($k, 'TEC_DATACLEANIG_SELECTED_TABLES') === 0) {
                     if (preg_match('/^TEC_DATACLEANIG_SELECTED_TABLES(?:[_\\[])?(.+?)(?:\\])?$/', $k, $m)) {
                         $tbl = $m[1];
                         if ($tbl !== '') {
                             $selectedTables[] = $tbl;
                         }
                     }
                 }
             }
             // Normalize and unique
             $selectedTables = array_values(array_unique(array_filter($selectedTables)));
              $batchSizeConfig = (int)Tools::getValue('TEC_DATACLEANIG_BATCH_SIZE');
             // Validate months value
             if ($monthsToKeep <= 0) {
                 $formErrors[] = $this->l('The number of months must be a positive value.');
             }

            // If there are errors, append them to output and skip persisting settings
            if (!empty($formErrors)) {
                foreach ($formErrors as $err) {
                    $output .= $this->displayError($err);
                }
                // Render the form so admin can correct values and return immediately (no success message)
                $output .= $this->renderForm();
                return $output;
            } else {
                // Persist the numeric settings
                Configuration::updateValue('TEC_DATACLEANIG_MONTHS', $monthsToKeep);
                // Save selected tables and batch size
                if (!empty($selectedTables) && is_array($selectedTables)) {
                    // Persist selected tables as JSON (safe alternative to serialize)
                    Configuration::updateValue('TEC_DATACLEANIG_SELECTED_TABLES', json_encode(array_values($selectedTables)));
                } else {
                    // ensure we clear the config if nothing selected
                    Configuration::deleteByName('TEC_DATACLEANIG_SELECTED_TABLES');
                }
                if ($batchSizeConfig > 0) {
                    Configuration::updateValue('TEC_DATACLEANIG_BATCH_SIZE', $batchSizeConfig);
                }
                // Persist secure key only now (after all validations passed)
                if (!empty($newPostedKey)) {
                    Configuration::updateValue('TEC_DATACLEANIG_SECURE_KEY', $newPostedKey);
                }
                $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
            }
         }

        if (Tools::isSubmit('submitCleanData')) {
            $tableName = Tools::getValue('table_name');
            $monthsToKeep = (int)Configuration::get('TEC_DATACLEANIG_MONTHS');

            $result = $this->cleanOldData($monthsToKeep, $tableName);

            if ($result['success']) {
                $output .= $this->displayConfirmation($result['message']);
            } else {
                $output .= $this->displayError($result['message']);
            }
        }

        if (Tools::isSubmit('submitCleanOrphanedData')) {
            $result = $this->cleanOrphanedData();

            if ($result['success']) {
                $output .= $this->displayConfirmation($result['message']);
            } else {
                $output .= $this->displayError($result['message']);
            }
        }

        $useSsl = (bool)Configuration::get('PS_SSL_ENABLED_EVERYWHERE') || (bool)Configuration::get('PS_SSL_ENABLED');
        $shop_base_url = $this->context->link->getBaseLink((int)$this->context->shop->id, $useSsl);

        $stats = $this->getTableStats();
        // Prepare config values for the form (stored as JSON). If not valid JSON we ignore it for security.
        $configuredTables = array();
        $cfg = Configuration::get('TEC_DATACLEANIG_SELECTED_TABLES');
        if (!empty($cfg)) {
            $decoded = json_decode($cfg, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $configuredTables = $decoded;
            } else {
                // Legacy serialized data (from older versions) is intentionally not unserialized for security reasons.
                // Admin can re-save selections in BO to migrate to the new JSON format.
                if (class_exists('Logger')) {
                    Logger::addLog('tec_datacleaning: selected tables config is not valid JSON; ignoring legacy serialized data for safety', 3);
                }
            }
        }
        $batchSizeConfig = (int)Configuration::get('TEC_DATACLEANIG_BATCH_SIZE');
        if ($batchSizeConfig < 1) {
            $batchSizeConfig = 1000;
        }

        $this->context->smarty->assign(array(
            'stats' => $stats,
            'module_dir' => $this->_path,
            'module_name' => $this->name,
            'module' => $this,
            'months_to_keep' => Configuration::get('TEC_DATACLEANIG_MONTHS'),
            'shop_base_url' => $shop_base_url,
            'configured_tables' => $configuredTables,
            'batch_size' => $batchSizeConfig,
        ));


        // No DB-persisted secure key used. Token calculation is done inline below.
         $output .= $this->renderForm();
        // Render remaining admin templates if present
        $moduleDir = rtrim(_PS_MODULE_DIR_, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR;
        $tplTablestats = $moduleDir . 'views/templates/admin/tablestats.tpl';
        $tplCron = $moduleDir . 'views/templates/admin/cron_instructions.tpl';
        // Compute the token used for cron/auth: prefer md5(_COOKIE_KEY_ . module_name)
        $module_secure_key = $this->computeModuleSecureKey();
        // Assign module token to smarty (if needed by templates) — no debug panels
        $this->context->smarty->assign('module_secure_key', $module_secure_key);
        $tplCopyright = $moduleDir . 'views/templates/admin/copyright.tpl';
        if (is_file($tplTablestats)) {
            $output .= $this->context->smarty->fetch($tplTablestats);
        } else {
            $output .= "<!-- tec_datacleaning: missing template tablestats.tpl -->";
            if (class_exists('Logger')) {
                Logger::addLog('tec_datacleaning: tablestats.tpl not found at ' . $tplTablestats, 3);
            }
        }

        // cron instructions tpl
        if (is_file($tplCron)) {
            $output .= $this->context->smarty->fetch($tplCron);
        } else {
            $output .= "<!-- tec_datacleaning: missing template cron_instructions.tpl -->";
            if (class_exists('Logger')) {
                Logger::addLog('tec_datacleaning: cron_instructions.tpl not found at ' . $tplCron, 3);
            }
        }

        if (is_file($tplCopyright)) {
            $output .= $this->context->smarty->fetch($tplCopyright);
        } else {
            $output .= "<!-- tec_datacleaning: missing template copyright.tpl -->";
            if (class_exists('Logger')) {
                Logger::addLog('tec_datacleaning: copyright.tpl not found at ' . $tplCopyright, 3);
            }
        }

         return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitTec_datacleanigModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        // Get default fields
        $fields = $this->getConfigFormValues();
        // If the admin submitted the form, prefer the persisted Configuration value (so the admin sees the saved key)
        if (Tools::isSubmit('submitTec_datacleanigModule')) {
            $persisted = Configuration::get('TEC_DATACLEANIG_SECURE_KEY');
            if (!empty($persisted) && strcasecmp($persisted, 'NOKEY') !== 0) {
                $fields['TEC_DATACLEANIG_SECURE_KEY'] = $persisted;
            } else {
                // If persistence didn't occur (for example validation failed), prefer the posted value so admin can fix it
                if (isset($_POST['TEC_DATACLEANIG_SECURE_KEY'])) {
                    $postedKey = is_array($_POST['TEC_DATACLEANIG_SECURE_KEY']) ? '' : trim((string)$_POST['TEC_DATACLEANIG_SECURE_KEY']);
                    $fields['TEC_DATACLEANIG_SECURE_KEY'] = $postedKey;
                }
            }
        }

        $helper->tpl_vars = array(
            'fields_value' => $fields, /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        // Build dynamic query for allowed tables
        $allowedTables = $this->getAllowedTables();
        $tablesQuery = array();
        foreach ($allowedTables as $tbl) {
            $tablesQuery[] = array('table_name' => $tbl);
        }

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    // readonly secure key display
                    array(
                        'type' => 'text',
                        'label' => $this->l('Module secure key'),
                        'name' => 'TEC_DATACLEANIG_SECURE_KEY',
                        'required' => true,
                        'class' => 'fixed-width-xl',
                        'desc' => $this->l('Copy this value to use in your cron URL.'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('How long to keep the data'),
                        'desc' => $this->l('Indicates how long to keep connection data.'),
                        'name' => 'TEC_DATACLEANIG_MONTHS',
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array('id_option' => 1, 'name' => $this->l('01 Month (30 days)')),
                                array('id_option' => 3, 'name' => $this->l('03 Months (90 days)')),
                                array('id_option' => 6, 'name' => $this->l('06 Months')),
                                array('id_option' => 12, 'name' => $this->l('12 Months')),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'checkbox',
                        'label' => $this->l('Tables to clean'),
                        'desc' => $this->l('Select the tables you want to include in the cleaning process.'),
                        'name' => 'TEC_DATACLEANIG_SELECTED_TABLES',
                        'values' => array(
                            'query' => $tablesQuery,
                            'id' => 'table_name',
                            'name' => 'table_name'
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Batch size'),
                        'desc' => $this->l('Number of records to process in each batch.'),
                        'name' => 'TEC_DATACLEANIG_BATCH_SIZE',
                        'class' => 'fixed-width-xl',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        // Deserialize selected tables for the helper form
        $selectedTables = array();
        $selectedSerialized = Configuration::get('TEC_DATACLEANIG_SELECTED_TABLES');
        if (!empty($selectedSerialized)) {
            $decoded = json_decode($selectedSerialized, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $selectedTables = $decoded;
            } else {
                // Do not unserialize legacy data for security reasons; log and continue with empty selection.
                if (class_exists('Logger')) {
                    Logger::addLog('tec_datacleaning: could not parse selected tables config as JSON; legacy serialized data is ignored', 3);
                }
            }
        }

        // Build fields_value expected by HelperForm for checkbox inputs: create keys like 'TEC_DATACLEANIG_SELECTED_TABLES_<table>' => 1
        $fields = array(
            'TEC_DATACLEANIG_MONTHS' => Configuration::get('TEC_DATACLEANIG_MONTHS'),
            'TEC_DATACLEANIG_BATCH_SIZE' => Configuration::get('TEC_DATACLEANIG_BATCH_SIZE'),
        );

        foreach ($this->getAllowedTables() as $tbl) {
            $key = 'TEC_DATACLEANIG_SELECTED_TABLES_' . $tbl;
            $fields[$key] = in_array($tbl, $selectedTables) ? 1 : 0;
        }

        // Show persisted secure key in form if present; otherwise empty string to allow admin input
        $persisted = Configuration::get('TEC_DATACLEANIG_SECURE_KEY');
        $fields['TEC_DATACLEANIG_SECURE_KEY'] = !empty($persisted) && $persisted !== 'NOKEY' ? $persisted : '';

         return $fields;
     }

    public function computeModuleSecureKey()
    {
        // 1) If admin already set/edited a secure key in Configuration, use it
        $cfg = Configuration::get('TEC_DATACLEANIG_SECURE_KEY');
        if (!empty($cfg)) {
            return $cfg;
        }

        // 2) If PrestaShop cookie key is available, keep legacy behaviour (do NOT persist here)
        if (defined('_COOKIE_KEY_')) {
            return md5(_COOKIE_KEY_ . $this->name);
        }

        // 3) No cookie key and no persisted key: signal explicitly that no key is available
        return 'NOKEY';
    }

    public function getAllowedTables()
    {
        return array(
            _DB_PREFIX_ . 'connections',
            _DB_PREFIX_ . 'connections_page',
            _DB_PREFIX_ . 'connections_source',
            _DB_PREFIX_ . 'pagenotfound',
            _DB_PREFIX_ . 'guest',
            _DB_PREFIX_ . 'statssearch',
            _DB_PREFIX_ . 'log',
        );
    }
    private function getDateFields()
    {
        return array(
            _DB_PREFIX_ . 'connections' => 'date_add',
            _DB_PREFIX_ . 'connections_page' => 'time_start',
            _DB_PREFIX_ . 'connections_source' => 'date_add',
            _DB_PREFIX_ . 'pagenotfound' => 'date_add',
            _DB_PREFIX_ . 'guest' => null,
            _DB_PREFIX_ . 'statssearch' => 'date_add',
            _DB_PREFIX_ . 'log' => 'date_add',
        );
    }

    /**
     * Cleans data older than a certain number of months from a specified table.
     *
     * @param int $monthsToKeep Number of months to keep.
     * @param string $tableName Name of the table to clean.
     * @return array Result of the operation with status and message.
     */
    public function cleanOldData($monthsToKeep, $tableName, $options = array())
    {
        $monthsToKeep = (int)$monthsToKeep;

        // options: ['dry_run' => bool, 'batch_size' => int]
        $dryRun = !empty($options['dry_run']);
        $batchSize = isset($options['batch_size']) && (int)$options['batch_size'] > 0 ? (int)$options['batch_size'] : 1000;

        // Get allowed tables and date fields
        $allowedTables = $this->getAllowedTables();
        $dateFields = $this->getDateFields();

        // Check parameters
        if ($monthsToKeep > 0 && !empty($tableName) && in_array($tableName, $allowedTables)) {
            // Calculate the cutoff date
            $date = new DateTime();
            $date->modify('-' . $monthsToKeep . ' months');
            $cutoffDate = $date->format('Y-m-d H:i:s');

            // Get the database instance
            $db = Db::getInstance();

            // Verify table exists
            $tblCheck = $db->executeS("SHOW TABLES LIKE '" . pSQL($tableName) . "'");
            if (empty($tblCheck)) {
                return array(
                    'success' => false,
                    'message' => $this->l('Table does not exist:') . ' ' . $tableName,
                );
            }

            // Special handling for ps_guest table
            if ($tableName === _DB_PREFIX_ . 'guest') {
                // Subquery to find id_guest with recent connections
                $subQuery = 'SELECT DISTINCT `id_guest` FROM `' . bqSQL(_DB_PREFIX_ . 'connections') . '` WHERE `date_add` >= \'' . pSQL($cutoffDate) . '\'';

                // Count how many would be deleted
                $countSql = 'SELECT COUNT(*) FROM `' . bqSQL($tableName) . '` WHERE `id_guest` NOT IN (' . $subQuery . ')';
                $toDelete = (int)$db->getValue($countSql);

                if ($dryRun) {
                    return array(
                        'success' => true,
                        'dry_run' => true,
                        'to_delete' => $toDelete,
                        'message' => $this->l('Dry run: number of guest rows that would be deleted:') . ' ' . $toDelete,
                    );
                }

                // Delete in batches to avoid long locks
                $deleted = 0;
                while ($toDelete > 0) {
                    $before = (int)$db->getValue($countSql);

                    $sql = 'DELETE FROM `' . bqSQL($tableName) . '` WHERE `id_guest` NOT IN (' . $subQuery . ') LIMIT ' . (int)$batchSize;
                    $res = $db->execute($sql);
                    if ($res === false) {
                        return array(
                            'success' => false,
                            'message' => $this->l('Error occurred while cleaning guest data.')
                        );
                    }

                    $after = (int)$db->getValue($countSql);
                    $deleted += ($before - $after);

                    // Prevent infinite loop if no progress
                    if ($before === $after) {
                        break;
                    }

                    $toDelete = $after;
                }

                return array(
                    'success' => true,
                    'deleted' => $deleted,
                    'message' => $this->l('Guest data cleaned successfully.')
                );
            } else {
                // Proceed as usual for other tables
                if (isset($dateFields[$tableName]) && !empty($dateFields[$tableName])) {
                    $dateField = $dateFields[$tableName];

                    // Verify the date field exists
                    $colCheck = $db->executeS('SHOW COLUMNS FROM `' . bqSQL($tableName) . '` LIKE \'' . pSQL($dateField) . '\'');
                    if (empty($colCheck)) {
                        return array(
                            'success' => false,
                            'message' => $this->l('Date field not available for this table:') . ' ' . $dateField,
                        );
                    }

                    // Prepare count query
                    $countSql = 'SELECT COUNT(*) FROM `' . bqSQL($tableName) . '` WHERE `' . bqSQL($dateField) . '` < \'' . pSQL($cutoffDate) . '\'';
                    $toDelete = (int)$db->getValue($countSql);

                    if ($dryRun) {
                        return array(
                            'success' => true,
                            'dry_run' => true,
                            'to_delete' => $toDelete,
                            'message' => $this->l('Dry run: number of rows that would be deleted:') . ' ' . $toDelete,
                        );
                    }

                    // Delete in batches
                    $deleted = 0;
                    while ($toDelete > 0) {
                        $before = (int)$db->getValue($countSql);

                        $sql = 'DELETE FROM `' . bqSQL($tableName) . '` WHERE `' . bqSQL($dateField) . '` < \'' . pSQL($cutoffDate) . '\' LIMIT ' . (int)$batchSize;
                        $res = $db->execute($sql);
                        if ($res === false) {
                            return array(
                                'success' => false,
                                'message' => $this->l('Error occurred while cleaning data.')
                            );
                        }

                        $after = (int)$db->getValue($countSql);
                        $deleted += ($before - $after);

                        // Prevent infinite loop if no progress
                        if ($before === $after) {
                            break;
                        }

                        $toDelete = $after;
                    }

                    return array(
                        'success' => true,
                        'deleted' => $deleted,
                        'message' => $this->l('Data cleaned successfully.')
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => $this->l('Date field not available for this table.')
                    );
                }
            }
        } else {
            return array(
                'success' => false,
                'message' => $this->l('Invalid parameters. Please ensure the table name is correct.')
            );
        }
    }


    public function getTableStats()
    {
        $stats = array();
        $db = Db::getInstance();

        $allowedTables = $this->getAllowedTables();
        $dateFields = $this->getDateFields();

        foreach ($allowedTables as $tableName) {
            $tableStats = array();
            $tableStats['table_name'] = $tableName;

            // 1. Number of records
            $countSql = 'SELECT COUNT(*) FROM `' . bqSQL($tableName) . '`';
            $recordCount = (int)$db->getValue($countSql);
            $tableStats['record_count'] = $recordCount;

            // 2. Size in MB
            $sizeSql = 'SELECT DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\' AND TABLE_NAME = \'' . bqSQL($tableName) . '\'';
            $tableStatusArray = $db->executeS($sizeSql);

            if (!empty($tableStatusArray)) {
                $tableStatus = $tableStatusArray[0];
                $dataLength = $tableStatus['DATA_LENGTH'];
                $indexLength = $tableStatus['INDEX_LENGTH'];
                $totalSize = ($dataLength + $indexLength) / (1024 * 1024); // Convert to MB
                $tableStats['size_mb'] = round($totalSize, 2);
            } else {
                $tableStats['size_mb'] = 0;
            }

            // 3. Date of the oldest data
            if (isset($dateFields[$tableName]) && !empty($dateFields[$tableName])) {
                $dateField = $dateFields[$tableName];

                $dateSql = 'SELECT MIN(`' . bqSQL($dateField) . '`) as oldest_date FROM `' . bqSQL($tableName) . '`';
                $oldestDate = $db->getValue($dateSql);

                $tableStats['oldest_date'] = $oldestDate ? $oldestDate : 'n.d.';
            } else {
                $tableStats['oldest_date'] = 'n.d.';
            }

            // 4. Determine if the table is empty
            $tableStats['is_empty'] = ($recordCount === 0);

            $stats[] = $tableStats;
        }

        return $stats;
    }

    public function cleanOrphanedData()
    {
        $db = Db::getInstance();

        $sqlGuest = 'DELETE FROM `' . _DB_PREFIX_ . 'guest` 
                 WHERE `id_guest` NOT IN (
                     SELECT DISTINCT `id_guest` FROM `' . _DB_PREFIX_ . 'connections`
                 )';
        $resultGuest = $db->execute($sqlGuest);

        $sqlConnectionsPage = 'DELETE FROM `' . _DB_PREFIX_ . 'connections_page` 
                           WHERE `id_connections` NOT IN (
                               SELECT DISTINCT `id_connections` FROM `' . _DB_PREFIX_ . 'connections`
                           )';
        $resultConnectionsPage = $db->execute($sqlConnectionsPage);

        $sqlConnectionsSource = 'DELETE FROM `' . _DB_PREFIX_ . 'connections_source` 
                             WHERE `id_connections` NOT IN (
                                 SELECT DISTINCT `id_connections` FROM `' . _DB_PREFIX_ . 'connections`
                             )';
        $resultConnectionsSource = $db->execute($sqlConnectionsSource);

        return array(
            'success' => true,
            'message' => $this->l('Orphan data cleanup completed successfully.')
        );
    }


}
