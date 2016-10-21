<?php namespace Rapide\VoipNow\Helpers;


/**
 * Created by PhpStorm.
 * User: Agnes
 * Date: 21-10-2016
 * Time: 10:46
 */

class Organization{

    private $id;
    private $name;
    private $firstName;
    private $lastName;
    private $email;
    private $login;
    private $identifier;

    public function __construct($id,$name,$firstName,$lastName,$login,$email,$identifier)
    {
        $this->name = $name;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->login = $login;
        $this->id = $id;
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

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setLogin($login)
    {
        $this->login = $login;
    }
}