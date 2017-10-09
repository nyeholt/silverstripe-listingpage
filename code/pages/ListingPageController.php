<?php

namespace Symbiote\ListingPage;

use PageController;

class ListingPageController extends PageController {
    private static $url_handlers = array(
        '$Action' => 'index'
    );

    public function index() {

        // This is required so the listing page doesn't eat AJAX requests against the page controller.
        $action = $this->getRequest()->latestParam('Action');
        if ($action && $this->hasMethod($action)) {
            return $this->$action();
        }
        else if (($this->data()->ContentType || $this->data()->CustomContentType)) {
            // k, not doing it in the theme...
            $contentType = $this->data()->ContentType ? $this->data()->ContentType : $this->data()->CustomContentType;
            $this->response->addHeader('Content-type', $contentType);

            return $this->data()->Content();
        }
        return array();
    }
}
