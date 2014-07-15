<?php
/*
 * @author xuruiqi
 * @date   2014.06.28
 * @desc Default Page class
 */
class Phpfetcher_Page_Default extends Phpfetcher_Page_Abstract {

    protected static $_arrField2CurlOpt = array(
        /* bool */
        'include_header' => CURLOPT_HEADER,
        'exclude_body'   => CURLOPT_NOBODY,
        'is_post'        => CURLOPT_POST,
        'is_verbose'     => CURLOPT_VERBOSE,
        'return_transfer'=> CURLOPT_RETURNTRANSFER,

        /* int */
        'buffer_size'       => CURLOPT_BUFFERSIZE,
        'connect_timeout'   => CURLOPT_CONNECTTIMEOUT,
        'connect_timeout_ms' => CURLOPT_CONNECTTIMEOUT_MS,
        'dns_cache_timeout' => CURLOPT_DNS_CACHE_TIMEOUT,
        'max_redirs'        => CURLOPT_MAXREDIRS,
        'port'              => CURLOPT_PORT,
        'timeout'           => CURLOPT_TIMEOUT,
        'timeout_ms'        => CURLOPT_TIMEOUT_MS,

        /* string */
        'cookie'            => CURLOPT_COOKIE,
        'cookie_file'       => CURLOPT_COOKIEFILE,
        'cookie_jar'        => CURLOPT_COOKIEJAR,
        'post_fields'       => CURLOPT_POSTFIELDS,
        'url'               => CURLOPT_URL,
        'user_agent'        => CURLOPT_USERAGENT,
        'user_pwd'          => CURLOPT_USERPWD,

        /* array */
        'http_header'       => CURLOPT_HTTPHEADER,

        /* stream resource */
        'file'              => CURLOPT_FILE,

        /* function or a Closure */
        'write_function'    => CURLOPT_WRITEFUNCTION,
    );

    protected $_arrDefaultConf = array(
            'connect_timeout' => 10,
            'max_redirs'      => 10,
            'return_transfer' => 1,   //need this
            'timeout'         => 15,
            'url'             => NULL,
    );

    protected $_arrConf    = array();
    protected $_bolCloseCurlHandle = FALSE;
    protected $_curlHandle = NULL;
    protected $_dom        = NULL;
    protected $_domxpath   = NULL;
    //protected $_xml        = NULL;

    public function __construct() {
    }
    public function __destruct() {
        if ($this->_bolCloseCurlHandle) {
            curl_close($this->_curlHandle);
        }
    }

    public static function formatRes($data, $errcode, $errmsg = NULL) {
        if ($errmsg === NULL) {
            $errmsg = Phpfetcher_Error::getErrmsg($errcode);
        }
        return array('errcode' => $errcode, 'errmsg' => $errmsg, 'res' => $data);
    }

    /**
     * @author xuruiqi
     * @desc get configurations.
     */
    public function getConf() {
        return $this->_arrConf;
    }

    /**
     * @author xuruiqi
     * @param $key: specified field
     * @return
     *      bool  : false when field doesn't exist
     *      mixed : otherwise
     * @desc get a specified configuration.
     */
    public function getConfField($key) {
        if (isset($this->_arrConf[$key])) {
            return self::formatRes($this->_arrConf[$key], Phpfetcher_Error::ERR_SUCCESS);
        } else {
            return self::formatRes(NULL, Phpfetcher_Error::ERR_FIELD_NOT_SET);
        }
    }

    public function getContent() {
        return $this->_strContent;
    }

    /**
     * @author xuruiqi
     * @param
     * @return
     *      string : current page's url
     * @desc get this page's URL.
     */
    public function getUrl() {
        return $this->getConfField['url'];
    }

    /**
     * @author xuruiqi
     * @param
     *      array $conf : configurations
     *      bool  $clear_default : whether to clear default options not set in $conf
     * @return
     * @desc initialize this instance with specified or default configuration
     */
    public function init($curl_handle = NULL, $conf = array()) {
        $this->_curlHandle = $curl_handle;
        if (empty($this->_curlHandle)) {
            $this->_curlHandle = curl_init();
            $this->_bolCloseCurlHandle = TRUE;
        }
        $this->_arrConf = $this->_arrDefaultConf;

        $this->msetConf($conf, TRUE);

        return $this;
    }

    /**
     * @author xuruiqi
     * @param
     *      array $ids : elements' ids
     * @return
     *      array : array of DOMElement, with keys equal each of ids
     *      NULL  : if $this->_dom equals NULL
     * @desc select spcified elements with their ids.
     */
    public function mselId($ids) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        $arrOutput = array();
        foreach ($ids as $id) {
            $arrOutput[$id] = $this->selId($id);
        }
        return $arrOutput;
    }

    /**
     * @author xuruiqi
     * @param
     *      array $tags : elements' tags
     * @return
     *      array : array of DOMNodeList, with keys equal each of tags 
     *      NULL  : if $this->_dom equals NULL
     * @desc select spcified elements with their tags
     */
    public function mselTagName($tags) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        $arrOutput = array();
        foreach ($tags as $tag) {
            $arrOutput[$tag] = $this->selId($tag);
        }
        return $arrOutput;
    }
    

    /**
     * @author xuruiqi
     * @param
     *      array $conf : configurations
     *      bool  $clear_previous_conf : if TRUE, then before set $conf, reset current configuration to its default value
     * @return
     *      array : previous conf
     * @desc set configurations.
     */
    public function msetConf($conf = array(), $clear_previous_conf = FALSE) {
        $previous_conf = $this->_arrConf;
        if ($clear_previous_conf === TRUE) {
            $this->_arrConf = $this->_arrDefaultConf;
        }
        foreach ($conf as $k => $v) {
            $this->_arrConf[$k] = $v;
        }

        $bolRes = TRUE;
        if ($clear_previous_conf === TRUE) {
            $bolRes = $this->_msetConf($this->_arrConf);
        } else {
            $bolRes = $this->_msetConf($conf);
        }

        if ($bolRes != TRUE) {
            $this->_arrConf = $previous_conf;
            $this->_msetConf($this->_arrConf);
            return $bolRes;
        }

        return $previous_conf;
    }

    protected function _msetConf($conf = array()) {
        $arrCurlOpts = array();
        foreach ($conf as $k => $v) {
            if (isset(self::$_arrField2CurlOpt[$k])) {
                $arrCurlOpts[self::$_arrField2CurlOpt[$k]] = $v;
            } else {
                //currently only curl options can be set
                $arrCurlOpts[$k] = $v;
            }
        }
        return curl_setopt_array($this->_curlHandle, $arrCurlOpts);
    }

    /**
     * @author xuruiqi
     * @param
     *      string $id : specifed element id
     * @return
     *      object : DOMElement or NULL is not found
     *      NULL   : if $this->_dom equals NULL
     * @desc select a spcified element via its id.
     */
    public function selId($id) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        return $this->_dom->getElementById($id);
    }

    /**
     * @author xuruiqi
     * @param
     *      string $tag : specifed elements' tag name 
     * @return
     *      object : a traversable DOMNodeList object containing all the matched elements
     *      NULL   : if $this->_dom equals NULL
     * @desc select spcified elements via its tag name.
     */
    public function selTagName($tag) {
        if ($this->_dom === NULL) {
            Phpfetcher_Log::warning('$this->_dom is NULL!');
            return NULL;
        }

        return $this->_dom->getElementsByTagName($tag);
    }

    public function setConf($field, $value) {
        $this->_arrConf[$field] = $value;
        return $this->_setConf($field, $value);
    }

    protected function _setConf($field, $value) {
        if (isset(self::$_arrField2CurlOpt[$field])) {
            return curl_setopt($this->_curlHandle, self::$_arrField2CurlOpt[$field], $value);
        } else {
            //currently only curl options can be set
            return curl_setopt($this->_curlHandle, $field, $value);
        }
    }

    /**
     * @author xuruiqi
     * @param
     *      string $url : the URL
     * @return
     *      string : previous URL
     * @desc set this page's URL.
     */
    public function setUrl($url) {
        $previous_url = $this->_arrConf['url'];
        $this->setConf('url', $url);
        return $previous_url;
    }

    /**
     * @author xuruiqi
     * @param
     * @return
     *      string : return page's content
     *      bool   : if failed return FALSE
     * @desc get page's content, and save it into member variable <_strContent>
     */
    public function read() {
        $this->_strContent = curl_exec($this->_curlHandle);
        if ($this->_strContent != FALSE) {

            $this->_dom = new DOMDocument();
            if ($this->_dom->loadHTML($this->_strContent) == FALSE) {
                Phpfetcher_Log::warning('Failed to call $this->_dom->loadHTML');
                $this->_dom      = NULL;
                $this->_domxpath = NULL;
            } else {
                $this->_domxpath = new DOMXPath($this->_dom);
            }

            /*
            $this->_xml = simplexml_load_string($strContent);
            if ($this->_xml == FALSE) {
                Phpfetcher_Log::warning('simplexml_load_string returned false');
                $this->_xml = NULL;
            }
            */
        }
        return $this->_strContent;
    }

    /**
     * @author xuruiqi
     * @param
     *      string $strPath : xpath's path
     * @return
     *      array : array of SimpleXMLElements objects
     *      NULL  : if $this->_xml equals NULL
     *      false : if error occurs
     * @desc select corresponding content use xpath
     */
    /*
    public function xpath($strPath) {
        if ($this->_xml === NULL) {
            Phpfetcher_Log::warning('$this->_xml is NULL!');
            return NULL;
        }

        return $this->_xml->xpath($strPath);
    }
    */

    /**
     * @author xuruiqi
     * @param
     *      string $strPath : xpath's path
     *      [DOMNode $contextnode : The optional contextnode can be specified for doing relative XPath queries. By default, the queries are relative to the root element.]
     *
     * @return
     *      DOMNodelist : DOMNodelist object
     *      NULL  : if $this->_domxpath equals NULL
     *      false : if error occurs
     * @desc select corresponding content use xpath
     */
    public function xpath($strPath, $contextnode = NULL) {
        if ($this->_domxpath === NULL) {
            Phpfetcher_Log::warning('$this->_domxpath is NULL!');
            return NULL;
        }

        if ($contextnode !== NULL) {
            $res = $this->_domxpath->query($strPath, $contextnode);
        } else {
            $res = $this->_domxpath->query($strPath);
        }

        return $res;
    }
}
?>