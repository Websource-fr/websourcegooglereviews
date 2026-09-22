<?php
/**
 * Public "avis clients" page — real Google reviews + AggregateRating JSON-LD.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class WebsourceGooglereviewsAvisModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ssl = true;

    public function setMedia()
    {
        parent::setMedia();
        $this->context->controller->registerStylesheet(
            'ws-avis-css',
            'modules/' . $this->module->name . '/views/css/avis.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    public function initContent()
    {
        parent::initContent();

        $aggregate = WebsourceGooglereviews::getAggregate();
        $reviews = WebsourceGooglereviews::getReviews();

        $this->context->smarty->assign([
            'ws_aggregate' => $aggregate,
            'ws_reviews' => $reviews,
            'ws_page_url' => WebsourceGooglereviews::getPageUrl(),
            'ws_shop_name' => $this->context->shop->name,
        ]);

        $this->setTemplate('module:websourcegooglereviews/views/templates/front/avis.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->module->l('Avis clients'),
            'url' => WebsourceGooglereviews::getPageUrl(),
        ];
        return $breadcrumb;
    }
}
