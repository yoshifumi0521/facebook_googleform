<?php
/*
 * クローリング共通化ツール
 * 
 * 2011/08/16 17:01 tamaya.riku
 * 2011/12/12 12:11 tamaya.riku
 */
class CrawlerTool {
    var $min_wait = 3;
    var $max_wait = 7;
    var $encoding = '';
    
    
    var $record_cnt    = 0;
    var $page_cnt = 0;
    var $page_skip_cnt = 0;
    
    var $total_cnt    = 0;
    var $skip_cnt   = 0;
    var $failed_cnt   = 0;
    var $insert_cnt = 0;
    var $update_cnt = 0;
    var $ng_word_cnt = 0;
    var $no_type_id_cnt = 0;
    var $no_product_cnt = 0;
    var $no_jan_code_cnt = 0;
    var $no_stock_cnt = 0;
    var $no_shop_cnt = 0;
    var $color_match_cnt = 0;
    var $insert_product_cnt = 0;
    var $shop_id;
    var $shop_key;
    var $tmp_dir;
    var $log_file;
    var $worker_start_time;
    var $env;
    var $cookie;
    var $clear_cache = false;
    var $no_sleep = false;
    var $no_cache = false;
    var $usleep;
    var $silent = false;
    var $domain;
    var $expire = 3600;
    
    function CrawlerTool() {
        // 挿入時間かつ削除の基準となる時間を記録
        $this->worker_start_time = time();
        
        $date = date('Ymd');
    if (file_exists('/var/www/inc/MemcachedLib.class.php')) {
          require_once('/var/www/inc/MemcachedLib.class.php');
          $this->cache = new MemcachedLib();
    } elseif (class_exists('Memcached')) {
          $this->cache = new Memcached();
    } elseif (class_exists('sfConfig')) {
          require_once(sfConfig::get('sf_symfony_lib_dir').DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'sfFileCache.class.php');
      $this->cache = new sfFileCache();
    }
   }
    
    /*
     * 
     *
     */
    function curlGet($url, $options=null) {

        if (empty($options['filename'])) {
            if ($arr = explode('/', $url)) {
                $options['filename'] = $arr[count($arr)-1];
            } else {
                $options['filename'] = 'error.txt';
            }
        }
        
        if (!empty($options['download_dir'])) {
            $this->tmp_dir = '/' . trim($options['download_dir'], '/');
        }

        //$full_path = "{$this->tmp_dir}/{$options['filename']}";
        if(empty($options['skip'])) {
            // cURLを使ってダウンロード
            if (empty($options['silent'])) echo "Now downloading {$url} to {$this->tmp_dir}\n";
            $ch = curl_init();
            $user_agent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ja; rv:1.9.1.1) Gecko/20090715 Firefox/3.5.1 (.NET CLR 3.5.30729)';
            curl_setopt ($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //curl_setopt($ch, CURLOPT_VERBOSE, TRUE); 
            
            if (!empty($options['post'])) {
                curl_setopt ($ch,CURLOPT_POST,1);
                curl_setopt ($ch,CURLOPT_POSTFIELDS, $options['post']);
                curl_setopt ($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
            }

            if (!empty($options['user']) && !empty($options['pass'])) {
                curl_setopt($ch, CURLOPT_USERPWD, $options['user'].":".$options['pass']);
            }
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $buf = curl_exec($ch);
            curl_close($ch);
            /*

            $postfix = "";
            while(file_exists("{$this->tmp_dir}{$options['filename']}{$postfix}")) {
                if(!unlink("{$this->tmp_dir}{$options['filename']}{$postfix}")) {
                    $postfix = intval($postfix) + 1;
                }
            }
            //$full_path.=$postfix;

            // バッファの内容を指定ファイルに上書き
            //$fp = fopen($full_path, 'w');
            //fputs($fp, $buf);
            //fclose($fp);
            */
        }
 /*
        if(!file_exists($full_path)) {
            echo "[ERROR] {$full_path} file not found.\n";
            return false;
        } else {
            echo "{$full_path} Download success!\n";
        }
*/       
        return $buf;
    }

    /*
    function curlHeader($url, $options=null) {

        $curl = curl_init();
          curl_setopt($curl, CURLOPT_URL, $url);
          curl_setopt($curl, CURLOPT_FILETIME, true); //更新日時取得
          curl_setopt($curl, CURLOPT_NOBODY, true);
          curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        //curl_setopt ($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt ($curl, CURLOPT_HEADER, false); 
        curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true); // 返り値を文字列で返す
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $info['result'] = $result;
        curl_close($curl);
        
        return $info;
    }
    
    */



    function scrape($tag, $subject, $options = null) {
        $arr = explode(' ', $tag);
        $end_tag = $arr[0];
        if (preg_match("/<{$tag}.*?>(.*?)<\/{$end_tag}>/msi", $subject, $matches)) {
            return $matches[1];
        }
        return false;
    }

    function scrape_all($tag, $subject, $options = null) {
        $arr = explode(' ', $tag);
        $end_tag = $arr[0];
        if (preg_match_all("/<{$tag}.*?>(.*?)<\/{$end_tag}>/msi", $subject, $matches)) {
            return $matches[1];
        }
        return false;
    }
  
  function setDomain(&$url) {
     $arr = parse_url($url);
     if (!empty($arr['host'])) {
           if (empty($this->domain)) {
             $this->domain = 'http://'.$arr['host'];
           }
     } else {
           if (!empty($this->domain)) {
             $url = $this->domain.$url;
           }
     }
  }
  
    function curlCached($url, $options=null){
      
      $this->setDomain($url); // 参照渡し
        $this->page_cnt++;
        if (!isset($options['encoding'])) $options['encoding'] = $this->encoding;
      if (!isset($options['no_cache'])) $options['no_cache'] = $this->no_cache;
      if (!isset($options['no_sleep'])) $options['no_sleep'] = $this->no_sleep;
      if (!isset($options['silent'])) $options['silent'] = $this->silent;
      if (!isset($options['usleep'])) $options['usleep'] = $this->usleep;
        
        if (!$options['silent']) echo "request for $url\n";

        $key = "http:cache:" . $url;
        if (!empty($options['encoding'])) $key.= ":{$options['encoding']}";
        $val = $this->cache->get($key);
    
        if(!$val || !empty($options['no_cache'])){
            if (!empty($options['usleep'])) {
              echo "waiting for ".($options['usleep'] / 1000)." ms\n";
              usleep($options['usleep']);
            } elseif (empty($options['no_sleep'])) {
                $rand = rand($this->min_wait,$this->max_wait);
                echo "waiting for {$rand} seconds ";
                for($i=0;$i<$rand;$i++){
                    echo ">";
                    sleep(1);
                }
                echo "\n";
            }
            
            $val = $this->curlGet($url, $options);
            
            if (!empty($options['encoding'])) {
                $val = mb_convert_encoding($val, 'utf8', $options['encoding']);
            } 
            $this->cache->set($key, $val, $this->expire);
        } else {
          $this->page_skip_cnt++;
        }
        
        return $val;
  }
  
    function httpRequestCached($url, $options=null){
      $this->setDomain($url); // 参照渡し

      if (empty($this->req)) {
        // クローラー
        require_once('HTTP/Request.php');
        $this->req = new HTTP_Request();
//        $this->req->addHeader('User-Agent', 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; InfoPath.1)');
        $this->req->addHeader('User-Agent', 'Mozilla/5.0 (Windows; U; Windows NT 6.0; ja; rv:1.9.1.1) Gecko/20090715 Firefox/3.5.1 (.NET CLR 3.5.30729)');
        $this->req->setMethod(HTTP_REQUEST_METHOD_GET);
      }
      
      if (empty($options['silent'])) echo "request for $url\n";
      if (empty($options['encoding'])) $options['encoding'] = $this->encoding;

      $key = "http:cache:" . $url;
      if (!empty($options['encoding'])) $key.= ":{$options['encoding']}";
      $val = $this->cache->get($key);
      //$val = null; // テスト用
      
      if(!$val || !empty($options['no_cache'])){
          if (empty($options['sleepless'])) {
              $rand = rand($this->min_wait,$this->max_wait);
              echo "waiting for {$rand} seconds ";
              for($i=0;$i<$rand;$i++){
                  echo ">";
                  sleep(1);
              }
              echo "\n";
          }
          $this->req->setUrl($url);
          
          // Cookieを使って取得する場合
          if (!empty($options['post'])) {
              $this->req->setMethod(HTTP_REQUEST_METHOD_POST);
              if (is_array($options['post'])) {
                  echo "[Post parameter]";
                  foreach ($options['post'] as $key => $val) {
                      echo " {$key}: {$val}";
                      $this->req->addPostData($key, $val);
                  }
                  echo "\n";
              }
          }

          if (!empty($options['header'])) {
              foreach ($options['header']  as $key => $val) {
                  $this->req->addHeader($key, $val);
              }
          }
              $this->req->addHeader('Cookie', "{$this->cookie[0]['name']}={$this->cookie[0]['value']};");

          // Cookieを使って取得する場合
          if (!empty($options['use_cookie'])) {
              
              echo "getting browser cookie and adding header...\n";
              if (!$this->cookie) {
                  $response = $this->req->sendRequest();
                  $this->cookie = $this->req->getResponseCookies();
                  //var_dump($this->cookie);
                  $rand = rand($this->min_wait,$this->max_wait);
                  echo "waiting for {$rand} seconds ";
                  for($i=0;$i<$rand;$i++){
                      echo ">";
                      sleep(1);
                  }
                  echo "\n\n";
              }
              $this->req->addHeader('Cookie', "{$this->cookie[0]['name']}={$this->cookie[0]['value']};");
              /*
              if (PEAR::isError($response) && $_SERVER['HIKAKU_DEBUG  ']) {
                  echo $response->getMessage();
              }
              */
              //$code = intval($this->req->getResponseCode());
              //$header = $this->req->getResponseHeader();
              $response = $this->req->sendRequest();
          }
          
          $response = $this->req->sendRequest();
          $val = $this->req->getResponseBody();
          
          
          if (!empty($options['encoding'])) {
              $val = mb_convert_encoding($val, 'utf8', $options['encoding']);
          } 
          $this->cache->set($key, $val, $this->expire);
      }
      
      return $val;
    }
    
    function printLog() {
        $start_date = date("Y-m-d H:i:s", $this->worker_start_time);
        $end_date = date("Y-m-d H:i:s", time());

        $this->message = <<< EOD

RECORD    : {$this->record_cnt}
PAGE      : {$this->page_cnt}
PAGE_SKIP : {$this->page_skip_cnt}
----------------------------------------------
Worker Started date   : {$start_date}
Worker Completed date : {$end_date}

EOD;
        print $this->message;
    }
   

}