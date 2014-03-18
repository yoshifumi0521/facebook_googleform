<?php

class facebook_loginActions extends sfActions
{

    public function preExecute()
    {
        //TimeDump::setDebugMode();
        //TimeDump::timeLog('init');
        // ログインチェック
        $this->user = $this->getUser()->auth('facebook|redirect|index|login|word|error|copyright|dummystart|dummyend');

        $this->fb = new FacebookUtil();

        // GETとPOSTを合わせて$_GETで受ける。この1行を加えないとrewrite後のパラメータが取れない
        $_GET = $this->getRequest()->getParameterHolder()->getAll();

    }

    //ログインをするときにメソッド
    public function executeLogin()
    {
        $type = $this->getRequestParameter('type');
        $token = $this->getRequestParameter('token');

        // $http_method = $this->getRequest()->getMethod();
        // var_dump($http_method);
        //Facebookログインのみ
        $this->fb_oauth_url = $this->fb->getOauthUrl($type,$token);

        if (!empty($_GET['developer_type'])) $this->getUser()->set('developer_type', $_GET['developer_type']);
        if (!empty($_GET['provider_type'])) $this->getUser()->set('provider_type', $_GET['provider_type']);
        if (!empty($_GET['user_type'])) $this->getUser()->set('user_type', $_GET['user_type']);

        // if (empty($_GET['ref'])) {
        //   $this->getUser()->set('last_url', 'entry');
        // }
        //ここでFacebookにリダイレクトする。
        // var_dump($this->fb_oauth_url);
        // die();
        $this->redirect($this->fb_oauth_url);


    }

    //Facebook認証が終わって、コールバックして帰ってくるときの処理
    public function executeFacebook()
    {
        if (SF_DEBUG && !empty($_GET['error_code'])) {
          var_dump($_GET);
          die;
        }
        elseif(!empty($_GET['code']))
        {
            //タイプとトークンを得る。
            $type = $this->getRequestParameter('type');
            $token = $this->getRequestParameter('token', null);
           //  if (!$type) {
           //      if (!SF_DEBUG) {
           //          $this->setFlash('error_message_arr', 'Facebookログインタイプの値が不正です。');
           //          $this->redirect('error/message');
           //      }
           //  }

           // if(empty($_GET['code'])) {
           //      if (!SF_DEBUG) {
           //          $this->setFlash('error_message_arr', 'コールバックの値が不正です。');
           //          $this->redirect('error/message');
           //      }
           // }

            // Facebookからのパラメーターを取得する。
            $this->fb->getAccessToken($_GET['code'],$type,$token);

            //ユーザー情報を取得する。
            $this->fb->getMe();


            //友達の情報を取得
            $this->fb->getFriendIds();

            // $this->userがここで存在する場合はあるのか？
            if ($this->user)
            {
                //ログインしていて、ユーザー登録している場合
                $user = $this->user;
            }
            else if ($this->fb->me->id)
            {
                //ログインしていなくて、ユーザー登録している場合
                //Userのデータを取得する。
                $c = new Criteria();
                $c->add(UserPeer::FACEBOOK_ID, $this->fb->me->id);
                $c->addDescendingOrderByColumn('created_at');
                //ここでエラーがおこる。ひとまず、コメントアウト
                $user = UserPeer::doSelectOne($c);
            }

            #ユーザーが登録されていなかったら、空のUserを作成する。
            if(!$user)
            {
                //空のものオブジェクトをつくる。
                $user = new User();
            }

            //お客さんの場合。お客様の登録やログイン。
            if($type == 'client')
            {
                $user = $this->setFacebookData($this->fb,$user);
                var_dump($user);


            }
            //form
            else if($type == 'user')
            {



            }

        }
        else
        {
            //もう一度Facebookにリダイレクトする。
            $this->redirect($this->fb->getOauthUrl());
        }
    }

    //Facebookのデータをデータベースにいれる。ログインするたびにとおる。
    private function setFacebookData($data,$user)
    {
        //クライアントのデータをいれる。
        $user->setName($data->me->name);
        $user->setEmail($data->me->email);
        $user->setMyselfText($data->me->bio);
        //Facebookの基本情報
        $user->setFacebookName($data->me->name);
        $user->setFacebookId($data->me->id);
        $user->setFacebookLink($data->me->link);
        $user->setFacebookIcon($data->getIcon());
        $user->setFacebookEmail($data->me->email);
        $user->setFacebookBio($data->me->bio);
        $user->setFacebookBirthday($data->me->birthday);
        if($data->me->gender && $data->me->gender == '男性')
        {
            $user->setFacebookGender('male');
        }
        else if($data->me->gender && $data->me->gender == '女性')
        {
            $user->setFacebookGender('female');
        }
        //アクセストークンをいれる。毎回取得する。
        $user->setFacebookAccessToken($data->access_token);

        //友達の情報をいれる。
        $frined_ids = $this->fb->getFriendIds();
        $user->setFacebookFriends(implode(',', $frined_ids));

        //登録済みにする。
        $user->setRegistered(1);
        // 保存する。
        $user->save();
        return $user;

    }

    // private function saveEducationData($educations,$has_educations,$user)
    // {

    //     if(!$user->getRegisterd() or !$has_educations)
    //     {
    //         //すべてのeducationデータを削除する。
    //         if($has_educations)
    //         {
    //             foreach ($has_educations as $has_education) {
    //                 UserEducationPeer::doDelete($has_education);
    //             }
    //         }

    //         //あったらする処理。ない人もいるので。
    //         if($educations)
    //         {
    //             foreach ($educations as $education)
    //             {
    //                 $new_education = new UserEducation;
    //                 $new_education->setUserId($user->getId());
    //                 //大学高校名
    //                 if($education->school->name){ $new_education->setSchool($education->school->name);}
    //                 //yearをいれる。
    //                 if($education->year) { $new_education->setYear($education->year->name);}
    //                 //コース。ここは、ハッシュでいれる。
    //                 if($education->concentration){ $new_education->setCourse(json_encode($education->concentration));}
    //                 // educationをデータにいれる。
    //                 $user->addUserEducation($new_education);
    //             }
    //         }

    //     }


    // }

    // private function saveCareerData($careers,$has_careers,$user)
    // {
    //     if(!$user->getRegisterd() or !$has_careers)
    //     {
    //         //今までのすべて削除する。
    //         foreach ($has_careers as $has_career) {
    //             UserCareerPeer::doDelete($has_career);
    //         }

    //         if($careers)
    //         {
    //             foreach ($careers as $career)
    //             {
    //                 $new_career = new UserCareer;
    //                 $new_career->setUserId($user->getId());
    //                 //会社名
    //                 if($career->employer->name){ $new_career->setCompany($career->employer->name);};
    //                 //ポジション
    //                 if($career->position->name){ $new_career->setPosition($career->position->name);};
    //                 //説明description
    //                 if($career->description){$new_career->setDescription(json_encode($career->description));};
    //                 //start_data
    //                 if($career->start_date){$new_career->setStartDate($career->start_date);};
    //                 //end_data
    //                 if($career->end_date){$new_career->setEndDate($career->end_date);};
    //                 //データを保存する。
    //                 $user->addUserCareer($new_career);

    //             }
    //         }
    //     }

    // }





}





















?>