<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @group integration
 */
class PermissionControllerTest extends ControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/admin/permissions');
    }

    public function testIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/permissions');
    }

    public function testPermissions()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/permissions');
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_user_admin_permissions', 118);
        $this->assertPageActions($client, [
            //'back' => $this->createUrl('/admin/user/'),
            'create modal-ajax-form' => $this->createUrl('/admin/permissions/roles/create'),
            'help' => 'https://www.kimai.org/documentation/permissions.html'
        ]);

        $content = $client->getResponse()->getContent();
        // the english translation instead of the real system user role names
        self::assertStringContainsString('<th data-field="User" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="Teamlead" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="Administrator" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="System-Admin" class="alwaysVisible text-center">', $content);
    }

    public function testCreateRoleIsSecured()
    {
        $this->assertUrlIsSecured('/admin/permissions/roles/create');
    }

    public function testCreateRoleIsSecuredForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/permissions');
    }

    public function testCreateRole()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/permissions/roles/create');
        $form = $client->getCrawler()->filter('form[name=role]')->form();
        $client->submit($form, [
            'role' => [
                'name' => 'TEST_ROLE',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/permissions'));
        $client->followRedirect();

        $content = $client->getResponse()->getContent();
        // the english translation instead of the real system user role names
        self::assertStringContainsString('<th data-field="User" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="Teamlead" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="Administrator" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="System-Admin" class="alwaysVisible text-center">', $content);
        self::assertStringContainsString('<th data-field="TEST_ROLE" class="alwaysVisible text-center">', $content);
    }

    public function testDeleteRoleIsSecured()
    {
        $this->assertUrlIsSecured('/admin/permissions/roles/1/delete/sdfsdfsdfsd');
    }

    public function testDeleteRoleIsSecuredForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/permissions');
    }

    public function testDeleteRole()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/permissions/roles/create');
        $form = $client->getCrawler()->filter('form[name=role]')->form();
        $client->submit($form, [
            'role' => [
                'name' => 'TEST_ROLE',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/permissions'));
        $client->followRedirect();

        $roles = $this->getEntityManager()->getRepository(Role::class)->findAll();
        $id = null;
        foreach ($roles as $role) {
            if ($role->getName() === 'TEST_ROLE') {
                $id = $role->getId();
                break;
            }
        }

        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('<th data-field="TEST_ROLE" class="alwaysVisible text-center">', $content);

        // add user to role
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/roles');
        $form = $client->getCrawler()->filter('form[name=user_roles]')->form();
        $client->submit($form, [
            'user_roles[roles]' => [
                0 => 'ROLE_TEAMLEAD',
                2 => 'ROLE_SUPER_ADMIN',
                3 => 'TEST_ROLE'
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/roles'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $user = $this->getUserByName(UserFixtures::USERNAME_USER);
        $this->assertEquals(['ROLE_TEAMLEAD', 'ROLE_SUPER_ADMIN', 'TEST_ROLE', 'ROLE_USER'], $user->getRoles());

        /** @var CsrfToken $token */
        $token = static::$kernel->getContainer()->get('security.csrf.token_manager')->getToken('user_role_permissions');
        $this->request($client, '/admin/permissions/roles/' . $id . '/delete/' . $token->getValue());
        $this->assertIsRedirect($client, $this->createUrl('/admin/permissions'));
        $client->followRedirect();

        self::assertHasFlashDeleteSuccess($client);
        $content = $client->getResponse()->getContent();
        self::assertStringNotContainsString('<th data-field="TEST_ROLE" class="alwaysVisible text-center">', $content);

        // verify that role was removed from user
        $user = $this->getUserByName(UserFixtures::USERNAME_USER);
        $this->assertEquals(['ROLE_TEAMLEAD', 'ROLE_SUPER_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testSavePermissionIsSecured()
    {
        $this->assertUrlIsSecured('/admin/permissions/roles/1/view_user/1/asdfasdf', 'POST');
    }

    public function testSavePermissionIsSecuredForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/permissions');
    }

    public function testSavePermission()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/permissions/roles/create');
        $form = $client->getCrawler()->filter('form[name=role]')->form();
        $client->submit($form, [
            'role' => [
                'name' => 'TEST_ROLE',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/permissions'));
        $client->followRedirect();

        $em = $this->getEntityManager();
        $rolePermissions = $em->getRepository(RolePermission::class)->findAll();
        $this->assertEquals(0, \count($rolePermissions));

        $roles = $em->getRepository(Role::class)->findAll();
        $id = null;
        foreach ($roles as $role) {
            if ($role->getName() === 'TEST_ROLE') {
                $id = $role->getId();
                break;
            }
        }

        // create the permission
        $token = static::$kernel->getContainer()->get('security.csrf.token_manager')->getToken('user_role_permissions');
        $this->request($client, '/admin/permissions/roles/' . $id . '/view_user/1/' . $token->getValue(), 'POST');

        self::assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($result);
        self::assertArrayHasKey('token', $result);

        $rolePermissions = $em->getRepository(RolePermission::class)->findAll();
        $this->assertEquals(1, \count($rolePermissions));
        $permission = $rolePermissions[0];
        self::assertInstanceOf(RolePermission::class, $permission);
        self::assertEquals('view_user', $permission->getPermission());
        self::assertTrue($permission->isAllowed());
        self::assertEquals('TEST_ROLE', $permission->getRole()->getName());
        self::assertEquals($id, $permission->getRole()->getId());

        // flush the cache to prevent wrong results
        $em->clear();

        // update the permission
        $token = static::$kernel->getContainer()->get('security.csrf.token_manager')->getToken('user_role_permissions');
        $this->request($client, '/admin/permissions/roles/' . $id . '/view_user/0/' . $token->getValue(), 'POST');

        self::assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($result);
        self::assertArrayHasKey('token', $result);

        $rolePermissions = $em->getRepository(RolePermission::class)->findAll();
        $this->assertEquals(1, \count($rolePermissions));
        $permission = $rolePermissions[0];
        self::assertInstanceOf(RolePermission::class, $permission);
        self::assertEquals('view_user', $permission->getPermission());
        self::assertFalse($permission->isAllowed());
    }
}
