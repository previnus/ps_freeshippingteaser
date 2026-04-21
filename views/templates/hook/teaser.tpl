{if isset($fst_ajax_url)}
<div class="freeshipping-teaser"
     data-ajax-url="{$fst_ajax_url|escape:'html':'UTF-8'}"
     data-threshold="{$fst_threshold|string_format:'%.2f'}"
     data-currency="{$fst_currency|escape:'html':'UTF-8'}"
     data-teaser-tpl="{$fst_teaser_tpl|escape:'html':'UTF-8'}"
     data-success-tpl="{$fst_success_tpl|escape:'html':'UTF-8'}">
  {if $fst_qualified}
    <div class="fst-success">
      <span>{$fst_success_text|escape:'html':'UTF-8'}</span>
    </div>
  {else}
    <div class="fst-bar-wrap">
      <div class="fst-bar" style="width:{$fst_percent|intval}%"></div>
    </div>
    <p class="fst-message">{$fst_teaser_text|escape:'html':'UTF-8'}</p>
  {/if}
</div>
{/if}
