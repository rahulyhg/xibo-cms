<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008,2009 Daniel Garner and James Packer
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;
use baseDAO;
use database;
use JSON;
use Kit;
use Xibo\Entity\Page;
use Xibo\Entity\Permission;
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\PageFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\Form;


class UserGroup extends Base
{
    /**
     * Display page logic
     */
    function displayPage()
    {
        $this->getState()->template = 'usergroup-page';
    }

    /**
     * Group Grid
     * @SWG\Get(
     *  path="/usergroup",
     *  operationId="userGroupSearch",
     *  tags={"usergroup"},
     *  summary="UserGroup Search",
     *  description="Search User Groups",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="formData",
     *      description="Filter by UserGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userGroup",
     *      in="formData",
     *      description="Filter by UserGroup Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/UserGroup")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $user = $this->getUser();

        $filterBy = [
            'groupId' => $this->getSanitizer()->getInt('userGroupId'),
            'group' => $this->getSanitizer()->getString('userGroup')
        ];

        $groups = (new UserGroupFactory($this->getApp()))->query($this->gridRenderSort(), $this->gridRenderFilter($filterBy));

        foreach ($groups as $group) {
            /* @var \Xibo\Entity\UserGroup $group */

            // we only want to show certain buttons, depending on the user logged in
            if ($user->getUserTypeId() == 1) {
                // Edit
                $group->buttons[] = array(
                    'id' => 'usergroup_button_edit',
                    'url' => $this->urlFor('group.edit.form', ['id' => $group->groupId]),
                    'text' => __('Edit')
                );

                // Delete
                $group->buttons[] = array(
                    'id' => 'usergroup_button_delete',
                    'url' => $this->urlFor('group.delete.form', ['id' => $group->groupId]),
                    'text' => __('Delete')
                );

                $group->buttons[] = ['divider' => true];

                // Copy
                $group->buttons[] = array(
                    'id' => 'usergroup_button_copy',
                    'url' => $this->urlFor('group.copy.form', ['id' => $group->groupId]),
                    'text' => __('Copy')
                );

                $group->buttons[] = ['divider' => true];

                // Members
                $group->buttons[] = array(
                    'id' => 'usergroup_button_members',
                    'url' => $this->urlFor('group.members.form', ['id' => $group->groupId]),
                    'text' => __('Members')
                );

                // Page Security
                $group->buttons[] = array(
                    'id' => 'usergroup_button_page_security',
                    'url' => $this->urlFor('group.acl.form', ['id' => $group->groupId]),
                    'text' => __('Page Security')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = (new UserGroupFactory($this->getApp()))->countLast();
        $this->getState()->setData($groups);
    }

    /**
     * Form to Add a Group
     */
    function addForm()
    {
        $this->getState()->template = 'usergroup-form-add';
        $this->getState()->setData([
            'help' => [
                'add' => $this->getHelp()->link('UserGroup', 'Add')
            ]
        ]);
    }

    /**
     * Form to Add a Group
     * @param int $groupId
     */
    function editForm($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $this->getState()->template = 'usergroup-form-edit';
        $this->getState()->setData([
            'group' => $group,
            'help' => [
                'add' => $this->getHelp()->link('UserGroup', 'Edit')
            ]
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $groupId
     * @throws \Xibo\Exception\NotFoundException
     */
    function deleteForm($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkDeleteable($group))
            throw new AccessDeniedException();

        $this->getState()->template = 'usergroup-form-delete';
        $this->getState()->setData([
            'group' => $group,
            'help' => [
                'delete' => $this->getHelp()->link('UserGroup', 'Delete')
            ]
        ]);
    }

    /**
     * Adds a group
     */
    function add()
    {
        // Build a user entity and save it
        $group = new \Xibo\Entity\UserGroup();
        $group->group = $this->getSanitizer()->getString('group');
        $group->libraryQuota = $this->getSanitizer()->getInt('libraryQuota');

        // Save
        $group->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $group->group),
            'id' => $group->groupId,
            'data' => $group
        ]);
    }

    /**
     * Edits the Group Information
     * @param int $groupId
     */
    function edit($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $group->group = $this->getSanitizer()->getString('group');
        $group->libraryQuota = $this->getSanitizer()->getInt('libraryQuota');

        // Save
        $group->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $group->group),
            'id' => $group->groupId,
            'data' => $group
        ]);
    }

    /**
     * Deletes a Group
     * @param int $groupId
     * @throws \Xibo\Exception\NotFoundException
     */
    function delete($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkDeleteable($group))
            throw new AccessDeniedException();

        $group->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * ACL Form for the provided GroupId
     * @param int $groupId
     */
    public function aclForm($groupId)
    {
        // Check permissions to this function
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        // Use the factory to get all the entities
        $entities = (new PageFactory($this->getApp()))->query();

        // Load the Group we are working on
        // Get the object
        if ($groupId == 0)
            throw new \InvalidArgumentException(__('ACL form requested without a User Group'));

        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        // Get all permissions for this user and this object
        $permissions = (new PermissionFactory($this->getApp()))->getByGroupId('Page', $groupId);

        $checkboxes = array();

        foreach ($entities as $entity) {
            /* @var Page $entity */
            // Check to see if this entity is set or not
            $entityId = $entity->getId();
            $viewChecked = 0;

            foreach ($permissions as $permission) {
                /* @var Permission $permission */
                if ($permission->objectId == $entityId && $permission->view == 1) {
                    $viewChecked = 1;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $entityId,
                'name' => $entity->title,
                'value_view' => $entityId . '_view',
                'value_view_checked' => (($viewChecked == 1) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $data = [
            'title' => sprintf(__('ACL for %s'), $group->group),
            'groupId' => $groupId,
            'group' => $group->group,
            'permissions' => $checkboxes,
            'help' => $this->getHelp()->link('User', 'Acl')
        ];

        $this->getState()->template = 'usergroup-form-acl';
        $this->getState()->setData($data);
    }

    /**
     * ACL update
     * @param int $groupId
     */
    public function acl($groupId)
    {
        // Check permissions to this function
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        // Load the Group we are working on
        // Get the object
        if ($groupId == 0)
            throw new \InvalidArgumentException(__('ACL form requested without a User Group'));

        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        // Use the factory to get all the entities
        $entities = (new PageFactory($this->getApp()))->query();

        // Get all permissions for this user and this object
        $permissions = (new PermissionFactory($this->getApp()))->getByGroupId('Page', $groupId);
        $objectIds = $this->getApp()->request()->params('objectId');

        if (!is_array($objectIds))
            throw new \InvalidArgumentException(__('Missing New ACL'));

        $newAcl = array();
        array_map(function ($string) use (&$newAcl) {
            $array = explode('_', $string);
            return $newAcl[$array[0]][$array[1]] = 1;
        }, $objectIds);

        $this->getLog()->debug(var_export($newAcl, true));

        foreach ($entities as $page) {
            /* @var Page $page */
            // Check to see if this entity is set or not
            $objectId = $page->getId();
            $permission = null;
            $view = (array_key_exists($objectId, $newAcl));

            // Is the permission currently assigned?
            foreach ($permissions as $row) {
                /* @var \Xibo\Entity\Permission $row */
                if ($row->objectId == $objectId) {
                    $permission = $row;
                    break;
                }
            }

            if ($permission == null) {
                if ($view) {
                    // Not currently assigned and needs to be
                    $permission = (new PermissionFactory($this->getApp()))->create($groupId, get_class($page), $objectId, 1, 0, 0);
                    $permission->save();
                }
            }
            else {
                $this->getLog()->debug('Permission Exists for %s, and has been set to %d.', $page->getName(), $view);
                // Currently assigned
                if ($view) {
                    $permission->view = 1;
                    $permission->save();
                }
                else {
                    $permission->delete();
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('ACL set for %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * Shows the Members of a Group
     * @param int $groupId
     */
    public function membersForm($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        // Users in group
        $usersAssigned = (new UserFactory($this->getApp()))->query(null, array('groupIds' => array($groupId)));

        // Users not in group
        $allUsers = (new UserFactory($this->getApp()))->query();

        // The available users are all users except users already in assigned users
        $checkboxes = array();

        foreach ($allUsers as $user) {
            /* @var User $user */
            // Check to see if it exists in $usersAssigned
            $exists = false;
            foreach ($usersAssigned as $userAssigned) {
                /* @var User $userAssigned */
                if ($userAssigned->userId == $user->userId) {
                    $exists = true;
                    break;
                }
            }

            // Store this checkbox
            $checkbox = array(
                'id' => $user->userId,
                'name' => $user->userName,
                'value_checked' => (($exists) ? 'checked' : '')
            );

            $checkboxes[] = $checkbox;
        }

        $this->getState()->template = 'usergroup-form-members';
        $this->getState()->setData([
            'group' => $group,
            'checkboxes' => $checkboxes,
            'help' =>  $this->getHelp()->link('UserGroup', 'Members')
        ]);
    }

    /**
     * Sets the Members of a group
     * @param int $groupId
     */
    public function assignUser($groupId)
    {
        $this->getLog()->debug('Assign User for groupId %d', $groupId);

        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $users = $this->getSanitizer()->getIntArray('userId');

        foreach ($users as $userId) {

            $this->getLog()->debug('Assign User %d for groupId %d', $userId, $groupId);

            $user = (new UserFactory($this->getApp()))->getById($userId);

            if (!$this->getUser()->checkViewable($user))
                throw new AccessDeniedException(__('Access Denied to User'));

            $group->assignUser($user);
        }

        // Check to see if unassign has been provided.
        $users = $this->getSanitizer()->getIntArray('unassignUserId');

        foreach ($users as $userId) {

            $this->getLog()->debug('Unassign User %d for groupId %d', $userId, $groupId);

            $user = (new UserFactory($this->getApp()))->getById($userId);

            if (!$this->getUser()->checkViewable($user))
                throw new AccessDeniedException(__('Access Denied to User'));

            $group->unassignUser($user);
        }

        $group->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Membership set for %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * Unassign a User from group
     * @param int $groupId
     */
    public function unassignUser($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkEditable($group))
            throw new AccessDeniedException();

        $users = $this->getSanitizer()->getIntArray('userId');

        foreach ($users as $userId) {
            $group->unassignUser((new UserFactory($this->getApp()))->getById($userId));
        }

        $group->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Membership set for %s'), $group->group),
            'id' => $group->groupId
        ]);
    }

    /**
     * Form to Copy Group
     * @param int $groupId
     */
    function copyForm($groupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($groupId);

        if (!$this->getUser()->checkViewable($group))
            throw new AccessDeniedException();

        $this->getState()->template = 'usergroup-form-copy';
        $this->getState()->setData([
            'group' => $group
        ]);
    }

    /**
     * @SWG\Post(
     *  path="/group/{userGroupId}/copy",
     *  operationId="userGroupCopy",
     *  tags={"usergroup"},
     *  summary="Copy User Group",
     *  description="Copy an user group, optionally copying the group members",
     *  @SWG\Parameter(
     *      name="userGroupId",
     *      in="path",
     *      description="The User Group ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="group",
     *      in="formData",
     *      description="The Group Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="copyMembers",
     *      in="formData",
     *      description="Flag indicating whether to copy group members",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/UserGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param int $userGroupId
     */
    public function copy($userGroupId)
    {
        $group = (new UserGroupFactory($this->getApp()))->getById($userGroupId);

        // Check we have permission to view this group
        if (!$this->getUser()->checkViewable($group))
            throw new AccessDeniedException();

        // Clone the group
        $group->load([
            'loadUsers' => ($this->getSanitizer()->getCheckbox('copyMembers') == 1)
        ]);
        $newGroup = clone $group;
        $newGroup->group = $this->getSanitizer()->getString('group');
        $newGroup->save();

        // Copy permissions
        foreach ((new PermissionFactory($this->getApp()))->getByGroupId('Page', $group->groupId) as $permission) {
            /* @var Permission $permission */
            $permission = clone $permission;
            $permission->groupId = $newGroup->groupId;
            $permission->save();
        }

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Copied %s'), $group->group),
            'id' => $newGroup->groupId,
            'data' => $newGroup
        ]);
    }
}
