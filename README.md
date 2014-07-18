# Slim Auth Example Implementation

#### Example implementation of the [Slim Auth library](https://github.com/jeremykendall/slim-auth)

## Requirements

In order to run this example implementation, you'll need to have the following
installed:

* [Vagrant](http://www.vagrantup.com/)
* [VirtualBox](https://www.virtualbox.org/)
* [Ansible](http://www.ansible.com/home) (See installation docs [here](http://docs.ansible.com/intro_installation.html#installing-the-control-machine))

## Usage

* Clone repo
* `cd /path/to/repo`
* Run `vagrant up`
* Add `192.168.56.102 slim-auth.dev` to `/etc/hosts`
* Open a browser and visit [http://slim-auth.dev](http://slim-auth.dev)

## The Database
The user database the example is using has the following schema:

    CREATE TABLE IF NOT EXISTS [users] (
        [id] INTEGER  NOT NULL PRIMARY KEY,
        [username] VARCHAR(50) NOT NULL,
        [role] VARCHAR(50) NOT NULL,
        [password] VARCHAR(255) NULL
    );

Pay special attention to the role column.  Without that, Slim Auth
won't work.

The user database contains two users: admin and member. Each has a 
role and password matching the username.

## Example ACL

In order to restrict access to application routes by role, we need to
create an ACL. The ACL extends `Zend\Permissions\Acl\Acl` 
(complete Zend ACL documentation can be found [here](http://framework.zend.com/manual/2.2/en/modules/zend.permissions.acl.intro.html)). The ACL is commented with a brief explanation of each section.

``` php
use Zend\Permissions\Acl\Acl as ZendAcl;

class Acl extends ZendAcl
{
    public function __construct()
    {
        // These are the roles in our application
        $this->addRole('guest');
        // member role "extends" guest, meaning the member role will get all of 
        // the guest role permissions by default
        $this->addRole('member', 'guest');
        $this->addRole('admin');

        // These are the resources in our app. The resources are the 
        // applications's route patterns
        $this->addResource('/');
        $this->addResource('/login');
        $this->addResource('/logout');
        $this->addResource('/member');
        $this->addResource('/admin');

        // Now we allow or deny a role's access to resources. The third argument
        // is 'privilege'. We're using HTTP method for resources.
        $this->allow('guest', '/', 'GET');
        $this->allow('guest', '/login', array('GET', 'POST'));
        $this->allow('guest', '/logout', 'GET');

        $this->allow('member', '/member', 'GET');

        // This allows admin access to everything
        $this->allow('admin');
    }
}
```
