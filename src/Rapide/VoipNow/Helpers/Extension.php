<?php namespace Rapide\VoipNow\Helpers;


/**
 * Created by PhpStorm.
 * User: Agnes
 * Date: 21-10-2016
 * Time: 10:46
 */

class Extension{

    private $name;
    private $firstName;
    private $lastName;
    private $extendedNumber;
    private $email;
    private $label;
    private $extensionType;
    private $identifier;

    public function __construct($name,$firstName,$lastName,$extendedNumber,$email,$label,$extensionType,$identifier)
    {
        $this->name = $name;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->extendedNumber = $extendedNumber;
        $this->label = $label;
        $this->email = $email;
        $this->extensionType = $extensionType;
        $this->identifier = $identifier;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        return $this->name = $name;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function setFirstName($firstName)
    {
        return $this->firstName = $firstName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setLastName($lastName)
    {
        return $this->lastName = $lastName;
    }

    public function getExtendedNumber()
    {
        return $this->extendedNumber;
    }

    public function setExtendedNumber($extendedNumber)
    {
        $this->extendedNumber = $extendedNumber;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getExtensionType()
    {
        return $this->extensionType;
    }

    public function setExtensionType($extensionType)
    {
        $this->extensionType = $extensionType;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function getExtension()
    {
        $number = $this->extendedNumber;
        $extension = substr($number, strpos($number, '*') +1);
        return $extension;
    }


}