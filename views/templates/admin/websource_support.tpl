{* Encart « accompagnement Websource » : back-office, super-administrateurs uniquement (voir supportBlock()). *}
<div class="panel ws-support" id="ws-support-websourcegooglereviews" data-key="wsSupport_websourcegooglereviews" role="complementary">
  <div class="panel-heading"><i class="icon-comments"></i> {l s='Besoin d\'aller plus loin ?' mod='websourcegooglereviews'}
    <span class="panel-heading-action"><button type="button" class="btn btn-link ws-support__hide" aria-label="{l s='Masquer pendant 30 jours' mod='websourcegooglereviews'}">{l s='Masquer' mod='websourcegooglereviews'} &times;</button></span>
  </div>
  <p>{l s='Websource, l\'agence éditrice de ce module, peut vous accompagner plus en profondeur : personnalisation sur mesure, adaptation à votre thème, développements spécifiques. Parlons de votre besoin.' mod='websourcegooglereviews'}</p>
  <p>
    <a class="btn btn-primary" href="{$ws_support_contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{l s='Nous contacter' mod='websourcegooglereviews'}</a>
    <a class="btn btn-default" href="{$ws_support_rdv|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{l s='Prendre rendez-vous' mod='websourcegooglereviews'}</a>
    <span class="text-muted">www.websource.fr</span>
  </p>
  <p class="text-muted small">{l s='En cliquant, le nom du module et l\'adresse de votre site sont transmis à Websource pour faciliter notre réponse.' mod='websourcegooglereviews'}</p>
</div>
<style>.ws-support{ldelim}border-left:4px solid #e8590c{rdelim}.ws-support .panel-heading-action{ldelim}float:right{rdelim}.ws-support__hide{ldelim}padding:0;color:#6c868e{rdelim}</style>
<script>
(function () {ldelim}
  var sup = document.getElementById('ws-support-websourcegooglereviews');
  if (!sup) {ldelim} return; {rdelim}
  var key = sup.getAttribute('data-key');
  try {ldelim}
    if (parseInt(localStorage.getItem(key) || '0', 10) > Date.now()) {ldelim} sup.style.display = 'none'; {rdelim}
  {rdelim} catch (e) {ldelim}{rdelim}
  var hb = sup.querySelector('.ws-support__hide');
  if (hb) {ldelim}
    hb.addEventListener('click', function () {ldelim}
      sup.style.display = 'none';
      try {ldelim} localStorage.setItem(key, String(Date.now() + 30 * 86400000)); {rdelim} catch (e) {ldelim}{rdelim}
    {rdelim});
  {rdelim}
{rdelim})();
</script>
