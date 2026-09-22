{*
 * Compact store-rating badge shown near the product title when this
 * product has no review of its own. Never claims the rating is about
 * this specific product — always framed as the store's overall rating.
 *}
<div class="ws-pdp-store-rating">
    <span class="ws-avis-stars" aria-hidden="true">
        {section name="s" start=0 loop=5 step=1}{if $smarty.section.s.index < $ws_pdp_aggregate.rating|round}★{else}☆{/if}{/section}
    </span>
    <a href="{$ws_pdp_page_url}" class="ws-pdp-store-rating-link">{$ws_pdp_aggregate.rating|string_format:"%.1f"|replace:'.':','}/5 &middot; {$ws_pdp_aggregate.count} avis {$ws_pdp_aggregate.source} de la boutique</a>
</div>
