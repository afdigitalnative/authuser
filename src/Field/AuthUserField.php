<?php

namespace Bolt\Extension\Marko\AuthUser\Field;

use Bolt\Storage\Field\FieldInterface;
use Bolt\Storage\Field\Type\FieldTypeBase;
use Doctrine\DBAL\Types\Type;
use Bolt\Storage\QuerySet;
use Bolt\Storage\EntityManager;

class AuthUserField extends FieldTypeBase
{
    public function getName()
    {
        return 'authuser';
    }
        
    public function getTemplate()
    {
        return 'authuser.twig';
    }

    public function getStorageType()
    {       
        return Type::getType('json_array');
    }

    public function getStorageOptions()
    {
        return ['notnull' => false];
    }
}
