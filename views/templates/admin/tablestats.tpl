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
        <i class="icon-table"></i> {$module->displayName|escape:'html':'UTF-8'} - {l s='Table Statistics' mod='tec_datacleaning'}
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{l s='Table Name' mod='tec_datacleaning'}</th>
                <th>{l s='Record Count' mod='tec_datacleaning'}</th>
                <th>{l s='Size (MB)' mod='tec_datacleaning'}</th>
                <th>{l s='Oldest Data Date' mod='tec_datacleaning'}</th>
                <th>{l s='Actions' mod='tec_datacleaning'}</th>
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
                                    <i class="icon-trash"></i> {l s='Clean' mod='tec_datacleaning'}
                                </button>
                            {else}
                                <button type="button" class="btn btn-danger btn-sm" disabled>
                                    <i class="icon-trash"></i> {l s='Clean' mod='tec_datacleaning'}
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
        <i class="icon-cogs"></i> {l s='Optimization and Cleaning' mod='tec_datacleaning'}
    </div>
    <form method="post" action="">
        <button type="submit" name="submitCleanOrphanedData" class="btn btn-warning">
            <i class="icon-broom"></i> {l s='Clear Orphaned Data' mod='tec_datacleaning'}
        </button>
    </form>
</div>
