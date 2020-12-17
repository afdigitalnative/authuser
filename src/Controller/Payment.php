<?php

namespace Bolt\Extension\Marko\AuthUser\Controller;

use \Bolt\Extension\BoltAuth\Auth\Storage\Entity\AccountMeta;
use \Bolt\Extension\BoltAuth\Auth\Storage\Records;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asynchronous route handling.
 *
 * Copyright (c) 2014-2016 Gawain Lynch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License or GNU Lesser
 * General Public License as published by the Free Software Foundation,
 * either version 3 of the Licenses, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 * @license   http://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License 3.0
 */
class Payment implements ControllerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        /** @var $ctr \Silex\ControllerCollection */
        $ctr = $app['controllers_factory'];

        $ctr->match('/complete', [$this, 'updateUserState'])
            ->bind('authUserPaymentComplete')
            ->method(Request::METHOD_POST);
        
		$ctr->match('/update-subscription', [$this, 'updateSubscriptionState'])
            ->bind('authUserPaymentUpdateSubscription')
            ->method(Request::METHOD_POST);

		$ctr->match('/update-user-date', [$this, 'updateUserDate'])
            ->bind('authUserPaymentUpdateUserDate')
            ->method(Request::METHOD_POST);
			
        return $ctr;
    }

    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return JsonResponse|\Twig_Markup
     */
    public function updateUserState(Application $app, Request $request)
    {
		// Get guid & subscriptionid from Request Post
		$guid = $request->request->get('guid');
		$subscriptionid = $request->request->get('subscriptionid');
		$subscriptiontype = $request->request->get('subscriptiontype');
		
		// Get the user entity and update role
		$records = new Records($app['auth.repositories']);
		$this->updateUserRole($records, $guid, ['active']);
		
		// Update the Registration/Expiration Date
		$this->updateUserMeta($records, $guid, 'registrationdate', date('m/d/Y'));
		$this->updateUserMeta($records, $guid, 'expirationdate', date('m/d/Y', strtotime('+1 years')));
		$this->updateUserMeta($records, $guid, 'subscriptionid', $subscriptionid);
		$this->updateUserMeta($records, $guid, 'subscriptiontype', $subscriptiontype);
		
		$return['error'] = false;
        return json_encode($return);
    }
	
    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return JsonResponse|\Twig_Markup
     */
    public function updateUserDate(Application $app, Request $request)
    {
		// Get guid & subscriptionid from Request Post
		$guid = $request->request->get('guid');
		
		// Get the user entity and update role
		$records = new Records($app['auth.repositories']);
		
		// Update the Registration/Expiration Date
		$this->updateUserMeta($records, $guid, 'registrationdate', date('m/d/Y'));
		$this->updateUserMeta($records, $guid, 'expirationdate', date('m/d/Y', strtotime('+1 years')));
		
		$return['error'] = false;
        return json_encode($return);
    }
	
    /**
     * @param Application $app
     * @param Request     $request
     *
     * @return JsonResponse|\Twig_Markup
     */
    public function updateSubscriptionState(Application $app, Request $request)
	{
		$qb = $app['storage']->createQueryBuilder();
		
		$event_type = $request->request->get('event_type');
		$resource = $request->request->get('resource');
		$subscriptionid = $resource['id'];
		
		$array_guid = $qb->select('guid')
					->from('bolt_auth_account_meta')
					->where('meta = :meta_name')
					->andWhere('value = :meta_value')
					->setParameter('meta_name', 'subscriptionid')
					->setParameter('meta_value', $subscriptionid)
					->execute()
					->fetchAll(); // ->andWhere('value = :subscriptionid')
		
		if(count($array_guid) > 0) {
			$guid = $array_guid[0]['guid'];
			
			if($guid != null) {
				$records = new Records($app['auth.repositories']);
				// If the subscription is renewed, update the registration / expiration date 
				if($event_type == 'BILLING.SUBSCRIPTION.RENEWED' || $event_type == 'BILLING.SUBSCRIPTION.RE-ACTIVATED' || $event_type == 'BILLING.SUBSCRIPTION.ACTIVATED') {
					$this->updateUserRole($records, $guid, ['active']);
					$this->updateUserMeta($records, $guid, 'registrationdate', date('m/d/Y'));
					$this->updateUserMeta($records, $guid, 'expirationdate', date('m/d/Y', strtotime('+1 years')));			
				}
				
				// If the subscription is cancelled, expired, failed
				if($event_type == 'BILLING.SUBSCRIPTION.CANCELLED' || $event_type == 'BILLING.SUBSCRIPTION.EXPIRED' || $event_type == 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' || $event_type == 'BILLING.SUBSCRIPTION.SUSPENDED') {
					$this->updateUserRole($records, $guid, ['inactive']);
				}
			}
		}
		
		// return json_encode($guid);
	}
	
    /**
     * @param Records $records
     * @param string     $meta_field
	 * @param string     $guid
     *
     * @return void
     */
    public function updateUserMeta(Records $records, $guid, $meta_field, $meta_value)
	{
		$metaEntity = $records->getAccountMeta($guid, $meta_field);
		if ($metaEntity === false) {
			$metaEntity = new AccountMeta();
		}
		$metaEntity->setGuid($guid);
		$metaEntity->setMeta($meta_field);
		$metaEntity->setValue($meta_value);
		$records->saveAccountMeta($metaEntity);		
	}
	
    /**
     * @param Records $records
     * @param string     $role
	 * @param string     $guid
     *
     * @return void
     */
	public function updateUserRole(Records $records, $guid, $role)
	{
		$user = $records->getAccountByGuid($guid);
        $user->setRoles($role);
		$records->saveAccount($user);		
	}
}
