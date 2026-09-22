{extends file='page.tpl'}

{block name='head_seo_title'}{strip}
  Avis clients - {$page.meta.title}
{/strip}{/block}

{block name='head_seo_description'}
  Ce que nos clients disent de {$ws_shop_name} : note moyenne de {$ws_aggregate.rating|string_format:"%.1f"|replace:'.':','}/5 sur {$ws_aggregate.count} avis {$ws_aggregate.source}.
{/block}

{block name='page_header_container'}
<header class="page-header">
  <h1 class="h1">Avis clients</h1>
</header>
{/block}

{block name='page_content'}
<div class="ws-avis-page">

  <div class="ws-avis-hero">
    <div class="ws-avis-summary">
      <span class="ws-avis-score">{$ws_aggregate.rating|string_format:"%.1f"|replace:'.':','}<span class="ws-avis-score-max">/5</span></span>
      <span class="ws-avis-stars" aria-hidden="true">
        {section name=star start=0 loop=5}
          {if $smarty.section.star.index < $ws_aggregate.rating|round}★{else}☆{/if}
        {/section}
      </span>
      <span class="ws-avis-count">{$ws_aggregate.count} avis {$ws_aggregate.source|escape:'html':'UTF-8'}</span>
    </div>
    {if $ws_aggregate.imported_at}
    <p class="ws-avis-source">
      Avis authentiques collectés sur notre fiche
      <strong>{$ws_aggregate.source|escape:'html':'UTF-8'}</strong>, mis à jour le
      {$ws_aggregate.imported_at|date_format:"%d/%m/%Y"}.
    </p>
    {/if}
  </div>

  {if $ws_reviews}
    <div class="ws-avis-grid">
      {foreach from=$ws_reviews item=review}
        <article class="ws-avis-card">
          <header class="ws-avis-card-head">
            <span class="ws-avis-author">{$review.author|escape:'html':'UTF-8'}</span>
            <span class="ws-avis-stars" aria-hidden="true">
              {section name=s start=0 loop=5}
                {if $smarty.section.s.index < $review.rating}★{else}☆{/if}
              {/section}
            </span>
          </header>
          {if $review.review_text}
            <p class="ws-avis-text">{$review.review_text|escape:'html':'UTF-8'|nl2br}</p>
          {/if}
          {if $review.age_text || $review.visited_text}
          <footer class="ws-avis-card-foot">
            {if $review.age_text}<span>{$review.age_text|escape:'html':'UTF-8'}</span>{/if}
            {if $review.visited_text}<span>Visité en {$review.visited_text|escape:'html':'UTF-8'}</span>{/if}
          </footer>
          {/if}
        </article>
      {/foreach}
    </div>
  {/if}

</div>
{/block}
