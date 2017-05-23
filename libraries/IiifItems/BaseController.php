<?php

/**
 * Template controller with plugin-specific utilities.
 * @package IiifItems
 */
abstract class IiifItems_BaseController extends Omeka_Controller_AbstractActionController {
    
    /**
     * Retrieves a record from the parameter-given ID (id) and type (things) and passes it to the view.
     * Renders 404 automatically if the given record is not found.
     * For use in routed actions only.
     * 
     * @throws Omeka_Controller_Exception_404
     */
    protected function __passModelToView() {
        //Get and check the thing's existence
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::camelize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing)) {
            throw new Omeka_Controller_Exception_404;
        }

        //Render view
        $this->view->type = $type;
        $this->view->thing = $thing;
    }

    /**
     * Helper for decomposing a IIIF OA annotation JSON into metadata-friendly parts.
     * 
     * @param array $json The IIIF OA annotation JSON
     * @return array 5-entry array with ID, attachment, SVG selector, body and array of tags
     */
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

    /**
     * Assembles metadata into IIIF OA annotation JSON data.
     * 
     * @param string $id The ID of the annotation
     * @param string $uri The URI of the annotation
     * @param string $selector The SVG selector of the annotation
     * @param string $body Text in the body of the annotation.
     * @param string[] $tags List of tags of the annotation.
     * @return array The reassembled OA annotation.
     */
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

    /**
     * Assembles metadata into IIIF OA annotation JSON string form.
     * 
     * @param string $id The ID of the annotation
     * @param string $uri The URI of the annotation
     * @param string $selector The SVG selector of the annotation
     * @param string $body Text in the body of the annotation.
     * @param string[] $tags List of tags of the annotation.
     * @return string The reassembled OA annotation string.
     */
    protected function __json_oa($id, $uri, $selector, $body, $tags) {
        return $this->__json_encode($this->__data_oa($id, $uri, $selector, $body, $tags));
    }

    /**
     * Assembles metadata into IIIF WA annotation JSON data.
     * 
     * @param string $id The ID of the annotation
     * @param string $uri The URI of the annotation
     * @param string $selector The SVG selector of the annotation
     * @param string $body Text in the body of the annotation.
     * @param string[] $tags List of tags of the annotation.
     * @return array The reassembled WA annotation.
     */
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

    /**
     * Assembles metadata into IIIF WA annotation JSON string form.
     * 
     * @param string $id The ID of the annotation
     * @param string $uri The URI of the annotation
     * @param string $selector The SVG selector of the annotation
     * @param string $body Text in the body of the annotation.
     * @param string[] $tags List of tags of the annotation.
     * @return string The reassembled WA annotation string.
     */
    protected function __json_wa($id, $uri, $selector, $body, $tags) {
        return $this->__json_encode($this->__data_wa($id, $uri, $selector, $body, $tags));
    }

    /**
     * Respond with JSON data (no layout).
     * 
     * @param array $jsonData JSON data in nested array form
     * @param integer $status The HTTP response code
     */
    protected function __respondWithJson($jsonData, $status=200) {
        $response = $this->getResponse();
        $this->_helper->viewRenderer->setNoRender();
        $response->setHttpResponseCode($status);
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Content-Type', 'application/json');
        $response->clearBody();
        $response->setBody($this->__json_encode($jsonData));
    }

    /**
     * Respond with raw data.
     * 
     * @param string $data Response data
     * @param integer $status The HTTP response code
     * @param string $mime The MIME type
     */
    protected function __respondWithRaw($data, $status=200, $mime='application/json') {
        $response = $this->getResponse();
        $this->_helper->viewRenderer->setNoRender();
        $response->setHttpResponseCode($status);
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Content-Type', $mime);
        $response->clearBody();
        $response->setBody($data);
    }

    /**
     * Encodes the argument in JSON.
     * Adds the unescaped slashes and unicode argument on PHP 5.4.0+
     * 
     * @param mixed $mixed
     * @return array
     */
    protected function __json_encode($mixed) {
        return version_compare(phpversion(), '5.4.0', '<')
            ? json_encode($mixed)
            : json_encode($mixed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Call this at the beginning of a controller action method to block unauthenticated users.
     * 
     * @throws Omeka_Controller_Exception_404
     */
    protected function __blockPublic() {
        if (!is_admin_theme()) {
            throw new Omeka_Controller_Exception_404;
        }
    }
    
    /**
     * Call this at the beginning of a controller action to block requests not made with the specified verb.
     * 
     * @param string $verb The allowed HTTP verb
     * @throws Omeka_Controller_Exception_404
     */
    protected function __restrictVerb($verb) {
        $request = $this->getRequest();
        if (strtolower($request->getMethod()) != strtolower($verb)) {
            throw new Omeka_Controller_Exception_404;
        }
    }
}
?>
