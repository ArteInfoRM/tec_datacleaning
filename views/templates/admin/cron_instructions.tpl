{*
  Cron instructions for Tec Datacleanig module
*}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-clock-o"></i> {l s='Cron Instructions' mod='tec_datacleaning'}
    </div>
    <div class="panel-body">
        <p>{l s='To automatically clean old data, configure a cron job that calls the module endpoint below.' mod='tec_datacleaning'}</p>

        <h4>{l s='Recommended endpoint' mod='tec_datacleaning'}</h4>
        <p>{l s='Use this URL. Replace values with those of your shop.' mod='tec_datacleaning'}</p>
        <pre style="background:#f8f8f8;padding:10px;border-radius:4px;">{$shop_base_url|escape:'html':'UTF-8'}module/{$module->name|escape:'html':'UTF-8'}/cron?secure_key={$module_secure_key|escape:'html':'UTF-8'}&amp;batch_size=500</pre>

        <h4>{l s='Curl example (dry-run: does not delete data)' mod='tec_datacleaning'}</h4>
        <pre style="background:#f8f8f8;padding:10px;border-radius:4px;">curl -s "{$shop_base_url|escape:'html':'UTF-8'}module/{$module->name|escape:'html':'UTF-8'}/cron?secure_key={$module_secure_key|escape:'html':'UTF-8'}&amp;dry_run=1"</pre>

        <h4>{l s='Crontab example (performs cleaning, batch_size=1000)' mod='tec_datacleaning'}</h4>
        <pre style="background:#f8f8f8;padding:10px;border-radius:4px;"># Run daily at 02:00
0 2 * * * curl -s "{$shop_base_url|escape:'html':'UTF-8'}module/{$module->name|escape:'html':'UTF-8'}/cron?secure_key={$module_secure_key|escape:'html':'UTF-8'}&amp;batch_size=1000" >/dev/null 2>&1</pre>

        <h4>{l s='Useful parameters' mod='tec_datacleaning'}</h4>
        <ul>
            <li><strong>secure_key</strong>: {l s='Module secure key (the cron will run only if this matches).' mod='tec_datacleaning'}</li>
            <li><strong>dry_run=1</strong>: {l s='Reports how many records would be deleted without executing the DELETE operations.' mod='tec_datacleaning'}</li>
            <li><strong>batch_size=N</strong>: {l s='Number of records to delete per batch (defaults to module configuration).' mod='tec_datacleaning'}</li>
        </ul>

        <h4>{l s='Truncate (fast cleanup)' mod='tec_datacleaning'}</h4>
        <p>{l s='You can perform a fast cleanup by truncating the selected statistic tables. This operation is destructive and removes all rows from the table.' mod='tec_datacleaning'}</p>
        <ul>
            <li>{l s='Parameter' mod='tec_datacleaning'}: <code>truncate=1</code></li>
            <li>{l s='Dry run for truncate' mod='tec_datacleaning'}: <code>truncate=1&amp;dry_run=1</code> — {l s='reports how many rows would be removed without executing TRUNCATE.' mod='tec_datacleaning'}</li>
            <li>{l s='Note' mod='tec_datacleaning'}: {l s='The module will always skip the log table (ps_log) to avoid losing logs.' mod='tec_datacleaning'}</li>
            <li>{l s='Batch size is ignored for TRUNCATE; TRUNCATE empties the table in a single fast operation.' mod='tec_datacleaning'}</li>
        </ul>

        <h5>{l s='Truncate examples' mod='tec_datacleaning'}</h5>
        <p>{l s='Dry-run (safe): will not change the DB, only returns counts' mod='tec_datacleaning'}</p>
        <pre style="background:#f8f8f8;padding:10px;border-radius:4px;">curl -s "{$shop_base_url|escape:'html':'UTF-8'}module/{$module->name|escape:'html':'UTF-8'}/cron?secure_key={$module_secure_key|escape:'html':'UTF-8'}&amp;truncate=1&amp;dry_run=1"</pre>

        <p>{l s='Real truncate (destructive) — make backups before running' mod='tec_datacleaning'}</p>
        <pre style="background:#f8f8f8;padding:10px;border-radius:4px;">curl -s "{$shop_base_url|escape:'html':'UTF-8'}module/{$module->name|escape:'html':'UTF-8'}/cron?secure_key={$module_secure_key|escape:'html':'UTF-8'}&amp;truncate=1"</pre>

        <p><strong>{l s='Truncate notes' mod='tec_datacleaning'}</strong></p>
        <ul>
            <li>{l s='TRUNCATE requires appropriate database privileges; if not available the operation will fail.' mod='tec_datacleaning'}</li>
            <li>{l s='TRUNCATE may be blocked by foreign key constraints on InnoDB tables; in that case consider using the regular cleaning (DELETE in batches) or adjust constraints carefully.' mod='tec_datacleaning'}</li>
            <li>{l s='Always execute a dry-run first and take a DB backup before performing real truncation.' mod='tec_datacleaning'}</li>
        </ul>

        <p><strong>{l s='Security note:' mod='tec_datacleaning'}</strong> {l s='Do not expose this URL without protection. Use secure_key and, if possible, restrict access by IP or additional authentication.' mod='tec_datacleaning'}</p>
    </div>
</div>
