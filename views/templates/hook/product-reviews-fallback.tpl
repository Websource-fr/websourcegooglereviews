{*
 * Product page footer fallback: shown only when the product has no
 * review of its own. Displays real store reviews, honestly labeled as
 * general store feedback (never as reviews of this specific product),
 * and emits NO product-level Review/AggregateRating JSON-LD — a store
 * rating misattributed to one product would be exactly the kind of
 * structured-data mismatch already fixed elsewhere on this site.
 *}
<section class="{if $ws_theme == 'classic' || $ws_theme == 'hummingbird'}ws-pdp-own-title{else}crossselling-products block block-section{/if} ws-pdp-reviews-fallback ws-theme-{$ws_theme|default:'other'|escape:'html':'UTF-8'}">
    {if $ws_theme == 'classic' || $ws_theme == 'hummingbird'}
    <h2 class="h4 ws-pdp-heading">Avis clients</h2>
    {else}
    <h4 class="section-title"><span>Avis clients</span></h4>
    {/if}
    <div class="block-content">
        <p class="ws-pdp-reviews-fallback-note">Ce produit n'a pas encore reçu d'avis. Voici ce que disent nos clients en général :</p>

        <div class="ws-avis-grid">
            {foreach from=$ws_pdp_picked_reviews item="review"}
                <article class="ws-avis-card">
                    <header class="ws-avis-card-head">
                        <span class="ws-avis-author">{$review.author|escape:'html':'UTF-8'}</span>
                        <span class="ws-avis-stars" aria-hidden="true">
                            {section name="s" start=0 loop=5 step=1}{if $smarty.section.s.index < $review.rating}★{else}☆{/if}{/section}
                        </span>
                    </header>
                    <p class="ws-avis-text">{$review.review_text|escape:'html':'UTF-8'}</p>
                </article>
            {/foreach}
        </div>

        <p class="ws-pdp-reviews-fallback-link">
            <a href="{$ws_pdp_page_url}">Voir tous nos avis {$ws_pdp_aggregate.source} ({$ws_pdp_aggregate.count})</a>
        </p>
    </div>
</section>
