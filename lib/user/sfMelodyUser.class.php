<?php
class sfMelodyUser extends sfGuardSecurityUser
{
  protected $tokens;

  public function connect($service, $force = false)
  {
    $oauth = sfMelody::getInstance($service);

    if(!$this->isConnected($service) || $force)
    {
      if($token = $this->getToken($service, Token::STATUS_REQUEST))
      {
        sfMelody::deleteTokens($service, $this->getGuardUser(), Token::STATUS_REQUEST);
      }

      if($this->isConnected($service) && $force)
      {
        sfMelody::deleteTokens($service, $this->getGuardUser(), Token::STATUS_ACCESS);
      }

      $this->setAttribute('callback_'.$service, $oauth->getCallback());
      $oauth->setCallback('@melody_access?service='.$service);
      $oauth->connect($this);
    }
    else
    {
      $oauth->getController()->redirect($oauth->getCallback());
    }
  }

  public function isConnected($service)
  {
    return !is_null($this->getToken($service, Token::STATUS_ACCESS));
  }

  public function getToken($service, $status = null, $session = false, $remove_in_session = false)
  {
    if($session)
    {
      $status = Token::STATUS_REQUEST?'request':'access';
      $token = $this->getAttribute($service.'_'.$status.'_token');
      if($remove_in_session && $token)
      {
        $this->getAttributeHolder()->remove($service.'_'.$status.'_token');
      }

      if($token)
      {
        return unserialize($token);
      }
      else
      {
        return null;
      }
    }

    $tokens = $this->getTokens();

    if(!is_null($status))
    {
      return isset($tokens[$status][$service])?$tokens[$status][$service]:null;
    }
    else
    {
      foreach(sfMelody::getTokenStatuses() as $status)
      {
        if(isset($tokens[$status][$service]))
        {
          return $tokens[$status][$service];
        }
      }
    }

    return null;
  }

  public function getTokens()
  {
    if(is_null($this->tokens) && $this->isAuthenticated())
    {
      $callable = array(sfMelody::getTokenOperationByOrm(), 'findByUserId');

      $tokens = call_user_func($callable, $this->getGuardUser()->getId());

      $this->tokens = array();
      foreach($tokens as $token)
      {
        $this->tokens[$token->getStatus()][$token->getName()] = $token;
      }
    }

    return $this->tokens;
  }

  public function getApi($service, $config = array(), $session)
  {
    $token = $this->getToken($service, Token::STATUS_ACCESS, $session);
    $config = array_merge($config, array('token' => $token));

    return sfMelody::getInstance($service, $config);
  }
}