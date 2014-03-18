<?php

class FacebookUtil {

  var $app_id;
  var $app_secret;
  var $redirect_url = '';
  var $access_token;
  var $me;

  public function FacebookUtil() {

    // クラスの実行テスト
    // config/custom.ymlからアプリキー取得
    $this->custom = Spyc::YAMLLoad(dirname(__FILE__).'/../config/custom.yml');
    // $this->custom = Spyc::YAMLLoad('');
    $this->app_id = $this->custom['core']['facebook_key']['app_id'];
    $this->app_secret = $this->custom['core']['facebook_key']['app_secret'];
    //設定がとおらないので、直接ここにapp_idを通した。
    // $this->app_id = "616626048360998";
    // $this->app_secret = "1d63d109e645acdf98e666539a61f8b3";

    if (!empty($this->custom['core']['facebook_key']['app_url'])) {
      $env = SF_DEBUG ? '/frontend_dev.php' : '';
      $this->redirect_url = trim($this->custom['core']['facebook_key']['app_url'], '/').$env.'/facebook_login/facebook';
    }

    if (class_exists('CrawlerTool')) {
      $this->crawler = new CrawlerTool();
      $this->crawler->no_sleep = true;
      $this->crawler->no_cache = true;
      $this->crawler->silent = true;
    }

    $this->scope = array(
      'email',
      //'read_stream',
      //'xmpp_login',
      //'offline_access', // 2012/02/20廃止
      'user_birthday',
      //'user_work_history',
      // 'user_location',
      //'user_likes',
      // 'user_education_history',//学歴
      //'friends_birthday',
      //'friends_work_history',
      //'friends_location',
      //'friends_likes',
      //'friends_education_history',
    );

  }

  public function getOauthUrl($type,$token = null) {
    //パラメーターでuser_typeを判定する。
    if($token)
    {
      $this->redirect_url = $this->redirect_url."?type=".$type."&token=".$token;
    }
    else
    {
      $this->redirect_url = $this->redirect_url."?type=".$type;
    }
    $oauth_url = 'http://www.facebook.com/dialog/oauth?client_id='
        . $this->app_id . '&redirect_uri=' . urlencode($this->redirect_url)
        . '&scope=' . implode(',', $this->scope);
    return $oauth_url;
  }

  public function getAccessToken($code='',$type,$token) {
    if (empty($code)) {
      $this->access_token = sfContext::getInstance()->getUser()->get('user')->getFacebookAccessToken();
    } else {
      $this->access_token = $code;
    }
    //short access token
    if($token)
    {
      $this->redirect_url = $this->redirect_url."?type=".$type."&token=".$token;
    }
    else
    {
      $this->redirect_url = $this->redirect_url."?type=".$type;
    }
    $token_url = 'https://graph.facebook.com/oauth/access_token?client_id='
      . $this->app_id . '&client_secret=' . $this->app_secret
      . '&redirect_uri=' . urlencode($this->redirect_url).'&code=' . $code;
    $response = $this->crawler->curlCached($token_url, array('no_cache' => true));
    $arr = explode('=', $response);
    $arr = explode('&', $arr[1]);

    if (!empty($arr[1])) {
      $this->access_token = $arr[0];
    }

    //long access token
    $token_url = 'https://graph.facebook.com/oauth/access_token?client_id='
      . $this->app_id . '&client_secret=' . $this->app_secret
      . '&redirect_uri=' . urlencode($this->redirect_url)
      .'&grant_type=fb_exchange_token&fb_exchange_token='.$this->access_token;
    $response = $this->crawler->curlCached($token_url, array('no_cache' => true));
    $arr = explode('=', $response);
    $arr = explode('&', $arr[1]);

    if (!empty($arr[1])) {
      $this->access_token = $arr[0];
    }
    // var_dump($this->access_token);

    // if(!empty($arr[1]) && !empty($arr[2]))
    // {
    //   $this->access_token = $arr[1].'='.$arr[2];
    // }
    // var_dump($this->access_token);
    // if (!empty($arr[1])) {
    //   $this->access_token = $arr[1];
    // }
  }

  public function getMe() {
    if (empty($this->access_token)) $this->getAccessToken();

    // $graph_url = 'https://graph.facebook.com/me?access_token=' . $this->access_token;
    //locale=ja_JPをつけ、日本語を取得する。
    $graph_url = 'https://graph.facebook.com/me?locale=ja_JP&access_token=' . $this->access_token;
    $this->me = json_decode($this->crawler->curlCached($graph_url));
  }

  public function getFriends($uid = '', $sub_query='') {
    if (empty($this->me)) $this->getMe();
    if (empty($uid)) $uid = $this->me->id;

     //$sub_query = 'and sex='female' ORDER BY rand() limit 8';
     //$fql = 'SELECT uid, name FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1=' . $uid.')' . $sub_query;
     $fql = 'SELECT id, name FROM profile WHERE id IN (SELECT uid2 FROM friend WHERE uid1=' . $uid.')' . $sub_query;
     $graph_url = 'https://api.facebook.com/method/fql.query?query='
       . rawurlencode($fql) . '&access_token=' . $this->access_token.'&format=json';
     $friend_arr = json_decode($this->crawler->curlCached($graph_url));

     return $friend_arr;
  }

  public function getGroupFriends($uid = '', $sub_query='') {
    if (empty($this->me)) $this->getMe();
    if (empty($uid)) $uid = $this->me->id;

     //$sub_query = 'and sex='female' ORDER BY rand() limit 8';
     //$fql = 'SELECT uid, name FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1=' . $uid.')' . $sub_query;
     $fql = 'SELECT uid, name,birthday,work,current_location,education  FROM user WHERE uid IN (SELECT uid2 FROM friend WHERE uid1=' . $uid.')' . $sub_query;
     $graph_url = 'https://api.facebook.com/method/fql.query?query='
       . rawurlencode($fql) . '&access_token=' . $this->access_token.'&format=json';
     $friend_arr = json_decode($this->crawler->curlCached($graph_url));

     return $friend_arr;
  }

  public function getFriendIds($sub_query='') {
    if (empty($this->access_token)) return false;

    $graph_url = 'https://api.facebook.com/method/friends.get?access_token=' . $this->access_token.'&format=json';
    return json_decode($this->crawler->curlCached($graph_url));
  }

  public function getAppUserIds() {
    if (empty($this->access_token)) return false;

    $graph_url = 'https://api.facebook.com/method/friends.getAppUsers?access_token=' . $this->access_token.'&format=json';
    return json_decode($this->crawler->curlCached($graph_url));
  }

  //テスト用
  public function mutualFriends($sub_query='') {
    if (empty($this->access_token)) return false;

    $graph_url = 'https://graph.facebook.com/me/mutualfriends/100002361096445?' . $this->access_token;
    $friend_arr = json_decode($this->crawler->curlCached($graph_url));
    return $friend_arr->data;
  }

  public function getProfile($uid = '') {
    if (empty($this->access_token)) $this->getAccessToken();

    if (!$uid) {
      $fql = 'SELECT name FROM profile WHERE id=me()';
    } else {
      $fql = 'SELECT name FROM profile WHERE id IN (SELECT uid2 FROM friend WHERE uid1=' . $uid.')';
    }
     $graph_url = 'https://api.facebook.com/method/fql.query?query='
       . rawurlencode($fql) . '&access_token=' . $this->access_token.'&format=json';

     $profile = json_decode($this->crawler->curlCached($graph_url));
     var_dump($profile);
     return $profile;
  }

  public function getNewsFeed($uid = '', $limit=100) {
    if (empty($this->access_token)) $this->getAccessToken();

    if (!$uid) {
     $graph_url = 'https://graph.facebook.com/me/feed';
    } else {
     $graph_url = 'https://graph.facebook.com/me/home';
    }
       $graph_url.= '?access_token=' . $this->access_token.'&format=json&limit='.$limit;
       $feeds = json_decode($this->crawler->curlCached($graph_url));
     return $feeds;
  }

  public function getIcon()
  {
    //プロフィール画像のiconのurlを返す。
    return 'https://graph.facebook.com/'.$this->me->id.'/picture';
  }

  //scopeを確認するためのメソッド
  public function checkScope()
  {
    $this->access_token = sfContext::getInstance()->getUser()->get('user')->getFacebookAccessToken();
    $graph_url = 'https://graph.facebook.com/me/permissions?access_token='.$this->access_token;
    $scope = json_decode($this->crawler->curlCached($graph_url));
    return $scope->data[0];
  }

  //scopeをとるために、
  public function redirectForScope($scope,$redirect_url)
  {
    return $url = 'http://www.facebook.com/dialog/oauth?client_id='
        . $this->app_id . '&redirect_uri=' . urlencode($redirect_url)
        .'&scope='.$scope;
  }

  function postFeed($comment,$picture,$name,$link,$description)
  {
      $this->access_token = sfContext::getInstance()->getUser()->get('user')->getFacebookAccessToken();
      //ウォール投稿API URL
      $post_url = 'https://graph.facebook.com/me/feed';
      $ch = curl_init();
      $param = array(
          'access_token' => $this->access_token,
          'message' => $comment,
          'picture' => $picture,
          'name' => $name,
          'link' => $link,
          'description'=> $description
      );

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_URL, $post_url);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
      $res = curl_exec($ch);
      curl_close($ch);
      return $res;
  }

  function send_facebook_notification($facebook_id,$comment,$url)
  {
    $post_url = 'https://graph.facebook.com/'.$facebook_id.'/notifications';
    $ch = curl_init();
    $param = array(
        'access_token' => $this->custom['core']['facebook_key']['app_access_token'],
        'template' => $comment,
        'href' => $url,
        'return_url' => $url
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_URL, $post_url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    $res = curl_exec($ch);
    curl_close($ch);
    // if($res[''])
    return $res['success'];

  }

  //テスト用
  public function getTestIds() {
      return array(
    '1242551058' => 'Tomokazu Hattanda',
    '100000794276497' => 'Kotomi Hattanda',
    '100000877776974' => 'Rino Omura',

    '100001447562889' => 'Masahiro Ueno',
    '100002361096445' => 'Jun Yokoyama',
    '100001975676401' => 'Masahiro Hiramitsu',
    '1187889054' => 'Dozoyoro Shiku',

    '1548204133' => 'Tatsuro Shimada',
    '100002006462689' => 'Tomoya Kuriyama',
    '100002012231843' => 'Yoichiro Mikami',
    '100002080988629' => 'Yu Suda',
    '100002107878325' => 'Noriaki Ogata',
    '100001006728305' => 'Ippei Kawana',
    '100002006462689' => 'Tomoya Kuriyama',
    '100002080988629' => 'Yu Suda',

    '100001671430667' => 'Jun Ikouga',
    '100000626006552' => 'Goda Takehiro',
    );
  }


}
