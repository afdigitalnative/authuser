<?php

namespace Bolt\Extension\Marko\AuthUser;

use Bolt\Extension\SimpleExtension;
use Bolt\Storage\Entity\Users;
use Bolt\Storage\Repository\ContentRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Bolt\Extension\BoltAuth\Auth\Storage;
use \Bolt\Extension\BoltAuth\Auth\Storage\Records;
use Bolt\Extension\Marko\AuthUser\Field\AuthUserField;
use Silex\Application;
use Bolt\Extension\BoltAuth\Auth\Event\FormBuilderEvent;
use Bolt\Extension\BoltAuth\Auth\Event\AuthProfileEvent;
use Bolt\Extension\BoltAuth\Auth\Event\AuthEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * AuthUser extension class.
 *
 * @author Marko Ivanovic
 */
class AuthUserExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addListener(AuthEvents::AUTH_PROFILE_REGISTER, [$this, 'onProfileSave']);        
    }
	
    /**
     * Tell Auth what fields we want to persist.
     *
     * @param AuthProfileEvent $event
     */
    public function onProfileSave(AuthProfileEvent $event)
    {
		$account = $event->getAccount();
		
		$to = "info@eacademics.com, gfilizetti@cawee.org, mesposito@cawee.org";
		$subject = "New member registration";

		$message = "
			<html>
			<head>
				<title>New member registration</title>
			</head>
			<body>
			<table style='border-collapse: collapse;'>
				<tr>
					<th style='border: 1px solid #aaa; background-color: #f2f2f2'>Name</th>
					<th style='border: 1px solid #aaa; background-color: #f2f2f2'>Email</th>
				</tr>
				<tr>
					<td style='border: 1px solid #aaa'>".$account->getDisplayname()."</td>
					<td style='border: 1px solid #aaa'>".$account->getEmail()."</td>
				</tr>
			</table>
			</body>
			</html>
		";

		// Always set content-type when sending HTML email
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

		// More headers
		$headers .= 'From: <cawee.org>' . "\r\n";
		//$headers .= 'Cc: myboss@example.com' . "\r\n";

		$result = mail($to,$subject,$message,$headers);
    }
	
    /**
     * {@registerTwigFunctions}
     */
    protected function registerTwigFunctions()
    {
        return [
            'fetch_auth_users' => 'fetchAuthUsers',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    protected function registerFrontendControllers()
    {
        $controllers = [
            '/process-payment' => new Controller\Payment(),
        ];

        return $controllers;
    }	
	
    protected function registerTwigPaths()
    {
        return [
            'templates',
        ];
    }    
 	
    /**
     * The callback function when {{ fetch_auth_users() }} is used in a template.
     *
     * @return array
     */
    public function fetchAuthUsers()
    {
		$app = $this->getContainer();
		
		/** Get the QueryBuilder Oject **/
		$qb = $app['storage']->createQueryBuilder();	
		
		/** Fetch the auth users by executing custom sql query **/
		// $auth_users = $qb->select('*')->from('bolt_auth_account')->execute()->fetchAll();	
	
		$records = new Records($app['auth.repositories']);
		// Storage\Records $records;
		$auth_users = $records->getAccounts();
		for($i = 0; $i < count($auth_users); $i++) {
			$user_meta = $records->getAccountMetaAll($auth_users[$i]->guid);
			
			foreach($user_meta as $meta) {
				$auth_users[$i][$meta->meta] = $meta->value;
			}
		}
		
        return $auth_users;		
    }
	
	/**
	* register new field type
	* @return object
	**/
    protected function registerFields()
    {
        return [
            new AuthUserField(),
        ];
    }	
}
