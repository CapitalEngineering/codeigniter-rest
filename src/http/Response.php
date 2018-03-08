<?php

namespace yidas\http;

use Exception;

/**
 * Response Component based on CI_Output
 * 
 * @author  Nick Tsai <myintaer@gmail.com>
 * @since   0.1.0
 * @example
 *  $response = new yidas\http\Response;
 *  $response->format = yidas\http\Response::FORMAT_JSON;
 *  $response->data = ['foo'=>'bar'];
 *  // $response->setStatusCode(200);
 *  $response->send();
 * @todo    Formatters
 */
class Response
{
    /**
     * @var string HTTP response formats
     */
    const FORMAT_RAW = 'raw';
    const FORMAT_HTML = 'html';
    const FORMAT_JSON = 'json';
    const FORMAT_JSONP = 'jsonp';
    const FORMAT_XML = 'xml';
    /**
     * @var object CI_Controller
     */
    public $ci;
    /**
     * @var array the formatters that are supported by default
     */
    public $contentTypes = [
        self::FORMAT_JSON => 'application/json;',
    ];
    /**
     * @var string the response format. This determines how to convert [[data]] into [[content]]
     * when the latter is not set. The value of this property must be one of the keys declared in the [[formatters]] array.
     * By default, the following formats are supported:
     *
     * - [[FORMAT_RAW]]: the data will be treated as the response content without any conversion.
     *   No extra HTTP header will be added.
     * - [[FORMAT_HTML]]: the data will be treated as the response content without any conversion.
     *   The "Content-Type" header will set as "text/html".
     * - [[FORMAT_JSON]]: the data will be converted into JSON format, and the "Content-Type"
     *   header will be set as "application/json".
     * - [[FORMAT_JSONP]]: the data will be converted into JSONP format, and the "Content-Type"
     *   header will be set as "text/javascript". Note that in this case `$data` must be an array
     *   with "data" and "callback" elements. The former refers to the actual data to be sent,
     *   while the latter refers to the name of the JavaScript callback.
     * - [[FORMAT_XML]]: the data will be converted into XML format. Please refer to [[XmlResponseFormatter]]
     *   for more details.
     *
     * You may customize the formatting process or support additional formats by configuring [[formatters]].
     * @see formatters
     */
    private $_format = self::FORMAT_JSON;
    /**
     * @var int the HTTP status code to send with the response.
     */
    private $_statusCode = 200;

    function __construct() 
    {
        // CI_Controller initialization
        $this->ci = & get_instance();
    }
    
    /**
     * Set Response Format into CI_Output
     * 
     * @param string Response format
     */
    public function setFormat($format)
    {
        $this->_format = $format;
        // Use formatter content type if exists
        if (isset($this->contentTypes[$this->_format])) {
            $this->ci->output
                ->set_content_type($this->contentTypes[$this->_format]);
        }

        return $this;
    }

    /**
     * Set Response Data into CI_Output
     * 
     * @param mixed Response data
     * @return object self
     */
    public function setData($data)
    {
        $formatFunc = $this->_format. "Format";
        // Use formatter if exists
        if (method_exists($this, $formatFunc)) {
            
            $data = $this->{$formatFunc}($data);
        } 
        elseif (is_array($data)) {
            // Prevent error if is array data for deafult
            $data = json_encode($data);
        }
        // CI Output
        $this->ci->output->set_output($data);

        return $this;
    }

    /**
     * Get Response Body from CI_Output
     * 
     * @return string Response body data
     */
    public function getOutput()
    {
        // CI Output
        return $this->ci->output->get_output();
    }

    /**
     * @return int the HTTP status code to send with the response.
     */
    public function getStatusCode()
    {
        return $this->_statusCode;
    }

    /**
     * Sets the response status code.
     * This method will set the corresponding status text if `$text` is null.
     * @param int $code the status code
     * @param string $text the status text. If not set, it will be set automatically based on the status code.
     * @throws Exception if the status code is invalid.
     * @return $this the response object itself
     */
    public function setStatusCode($code, $text = null)
    {
        if ($code === null) {
            $code = 200;
        }
        $this->_statusCode = (int) $code;
        if ($this->getIsInvalid()) {
            throw new Exception("The HTTP status code is invalid: $code");
        }
        // Set into CI_Output
        $this->ci->output->set_status_header($code, $text);

        return $this;
    }

    /**
     * @return bool whether this response has a valid [[statusCode]].
     */
    public function getIsInvalid()
    {
        return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
    }

    /**
     * Sends the response to the client.
     */
    public function send()
    {
        $this->ci->output->_display();
        exit;
    }

    /**
     * Common format funciton by format types. {FORMAT}Format()
     * 
     * @param array Pre-handle array data
     */
    public static function jsonFormat($data)
    {
        return json_encode($data);
    }

    /**
     * JSON output shortcut
     * 
     * @param array|mixed Callback data body, false will remove body key
     * @param int Callback status code
     * @param string Callback status text
     * @return string Response body data
     */
    public function json($data, $statusCode=null, $statusText=null)
    {
        // Set Status Code
        if ($statusCode) {
            $this->setStatusCode($statusCode, $statusText);
        }
        
        return $this->setFormat(Response::FORMAT_JSON)
            ->setData($data)
            ->send();
    }
}
