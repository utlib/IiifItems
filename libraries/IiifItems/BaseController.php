<?php
abstract class IiifItems_BaseController extends Omeka_Controller_AbstractActionController {
    protected function __passModelToView() {
        //Get and check the thing's existence
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::titleize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing)) {
            throw new Omeka_Controller_Exception_404;
        }

        //Render view
        $this->view->type = $type;
        $this->view->thing = $thing;
    }

    protected function __dissect_oa($json) {
        $params = json_decode($json, true);
        $id = $params['@id'];
        $on = $params['on']['full'];
        $selector = $params['on']['selector']['value'];
        $body = "";
        $tags = array();
        foreach ($params['resource'] as $resource) {
            switch ($resource['@type']) {
                case 'dctypes:Text': $body = $resource['chars']; break;
                case 'oa:Tag': $tags[] = $resource['chars']; break;
            }
        }
        return array($id, $on, $selector, $body, $tags);
    }

    protected function __data_oa($id, $uri, $selector, $body, $tags) {
        $data = array(
            "@id" => $id . "",
            "@context" => "http://iiif.io/api/presentation/2/context.json",
            "@type" => "oa:Annotation",
            "motivation" => array("oa:commenting", "oa:tagging"),
            "resource" => array(
                array(
                    "@type" => "dctypes:Text",
                    "format" => "text/html",
                    "chars" => $body,
                ),
            ),
            "on" => array(
                "@type" => "oa:SpecificResource",
                "full" => $uri,
                "selector" => array(
                    "@type" => "oa:SvgSelector",
                    "value" => $selector,
                ),
            ),
        );
        foreach ($tags as $tag) {
            $data['resource'][] = array(
                "@type" => "oa:Tag",
                "chars" => $tag,
            );
        }
        return $data;
    }

    protected function __json_oa($id, $uri, $selector, $body, $tags) {
        return $this->__json_encode($this->__data_oa($id, $uri, $selector, $body, $tags));
    }

    protected function __data_wa($id, $uri, $selector, $body, $tags) {
        $data = array(
            "@context" => "http://www.w3.org/ns/anno.jsonld",
            "id" => $id . "",
            "type" => "Annotation",
            "motivation" => "description",
            "body" => array(
                array(
                    "type" => "TextualBody",
                    "format" => "text/html",
                    "purpose" => "describing",
                    "value" => $body
                ),
            ),
            "target" => array(
                "source" => $uri,
                "selector" => array(
                    "type" => "SvgSelector",
                    "value" => $selector
                ),
            ),
        );
        foreach ($tags as $tag) {
            $data['body'][] = array(
                "type" => "textualBody",
                "purpose" => "tagging",
                "value" => $tag
            );
        }
        return $data;
    }

    protected function __json_wa($id, $uri, $selector, $body, $tags) {
        return $this->__json_encode($this->__data_wa($id, $uri, $selector, $body, $tags));
    }

    protected function __respondWithJson($jsonData, $status=200) {
        $response = $this->getResponse();
        $this->_helper->viewRenderer->setNoRender();
        $response->setHttpResponseCode($status);
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Content-Type', 'application/json');
        $response->clearBody();
        $response->setBody($this->__json_encode($jsonData));
    }

    protected function __respondWithRaw($data, $status=200, $mime='application/json') {
        $response = $this->getResponse();
        $this->_helper->viewRenderer->setNoRender();
        $response->setHttpResponseCode($status);
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Content-Type', $mime);
        $response->clearBody();
        $response->setBody($data);
    }

    protected function __json_encode($mixed) {
        return version_compare(phpversion(), '5.4.0', '<')
            ? json_encode($mixed)
            : json_encode($mixed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
?>
