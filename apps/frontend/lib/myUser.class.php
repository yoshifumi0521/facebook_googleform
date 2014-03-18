<?php

class myUser extends sfBasicSecurityUser
{
  public function myUser() {
    $this->namespace = '';
    $this->credential = 'free_user';
    $this->top = ''; // ログインしてないときのリダイレクト先を指定
    $this->rename = null;//'profile/rename'; // ここをnullにすればニックネーム入力強制しない
  }

  public function get($name, $value=null) {
    if ($name == 'user') {
      $context = sfContext::getInstance();
      if(!empty($context->user)) {
        return $context->user;
      } else {
        return $this->getRecord();
      }
    } else {
      return $this->getAttribute($name, $value, $this->namespace);
    }
  }

  public function set($name, $value) {
    return $this->setAttribute($name, $value, $this->namespace);
  }

  public function login($user) {
    $this->setAuthenticated(true);
    $this->addCredential($this->credential);
    $this->set('user_id', $user->getId());
    //1っ月
    sfContext::getInstance()->getResponse()->setCookie('user_id',$user->getId(),time()+3600*24*30,'/');

    $user = $this->get('user');
  }

  public function logout() {
    $this->setAuthenticated(false);
    $this->clearCredentials();
    $this->getAttributeHolder()->removeNamespace($this->namespace);
    sfContext::getInstance()->getResponse()->setCookie('user_id', '',time()- 3600*24*30, '/');
  }

  public function auth($open_list) {
    $context = sfContext::getInstance();

    $url_arr = parse_url(sfContext::getInstance()->getRequest()->getUri());
    $url_arr = explode('/',$url_arr['path']);

    //$url = '/'.$url_arr[1];
    $url = '';
    if (SF_DEBUG) $url.= '/frontend_dev.php';
    //if (!empty($url_arr[2]) && strpos($url_arr[2],'.php' !== false)) $url.= '/'.$url_arr[1];

    // 引数に追加されたアクション以外はログインしていなければトップにリダイレクト
    $user = $this->getRecord();
    // $module_arr = array('job','entry','mypage','dashboard');
    // if (in_array($context->getModuleName(),$module_arr)) {
    //   $this->set('last_url', $context->getRequest()->getUri());
    // }

    if (!$user && !in_array($context->getActionName(), (array)explode('|',$open_list))) {
      $this->set('last_url', $context->getRequest()->getUri());
      $context->getController()->redirect("{$url}/".$this->top);
      //すべてtopに飛ばす。
      //$context->getController()->redirect("{$url}/top");
    }

    // ログイン後にニックネーム入力を強制する
    if (!empty($this->rename) && $user && !$user->getNickName() && $context->getActionName() != 'rename') {
      $context->getController()->redirect("{$url}/".$this->rename);
    }

    return $user;
  }

  public function getRecord() {

    $user_id = sfContext::getInstance()->getRequest()->getCookie('user_id');
    if(!$user_id)
    {
      $user_id = $this->get('user_id');
    }
    if ($user_id) {
      $c = new Criteria();
      $user = UserPeer::retrieveByPk($user_id);
    } else {
      $user = null;
    }

    return $user;
  }






}
