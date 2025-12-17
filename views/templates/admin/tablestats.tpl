{*
**
*  2009-2024 Arte e Informatica
*
*  For support feel free to contact us on our website at https://www.tecnoacquisti.com/
*
*  @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
*  @copyright 2009-2024 Arte e Informatica
*  @version   1.0.0
*  @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
*
*}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-table"></i> {$module->displayName|escape:'html':'UTF-8'} - {$module->l('Table Statistics')}
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{$module->l('Table Name')}</th>
                <th>{$module->l('Record Count')}</th>
                <th>{$module->l('Size (MB)')}</th>
                <th>{$module->l('Oldest Data Date')}</th>
                <th>{$module->l('Actions')}</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$stats item=tableStat}
                <tr>
                    <td>{$tableStat.table_name|escape:'html':'UTF-8'}</td>
                    <td>{$tableStat.record_count|escape:'html':'UTF-8'}</td>
                    <td>{$tableStat.size_mb|escape:'html':'UTF-8'}</td>
                    <td>{$tableStat.oldest_date|escape:'html':'UTF-8'}</td>
                    <td class="table-stats-actions">
                        <form method="post" action="">
                            <input type="hidden" name="table_name" value="{$tableStat.table_name|escape:'html':'UTF-8'}">
                            {if !$tableStat.is_empty}
                                <button type="submit" name="submitCleanData" class="btn btn-danger btn-sm">
                                    <i class="icon-trash"></i> {$module->l('Clean')}
                                </button>
                            {else}
                                <button type="button" class="btn btn-danger btn-sm" disabled>
                                    <i class="icon-trash"></i> {$module->l('Clean')}
                                </button>
                            {/if}
                        </form>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {$module->l('Optimization and Cleaning')}
    </div>
    <form method="post" action="">
        <button type="submit" name="submitCleanOrphanedData" class="btn btn-warning">
            <i class="icon-broom"></i> {$module->l('Clear Orphaned Data')}
        </button>
    </form>
</div>

