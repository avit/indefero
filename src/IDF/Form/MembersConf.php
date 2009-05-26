<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


/**
 * Configuration of the members.
 *
 * To simplify the management. Instead of being obliged to go through
 * a list of people and then select the rights member/owner, I am
 * using the same approach as googlecode, that is, asking for the
 * login. This makes the interface simpler and simplicity is king.
 *
 * In background, the row permission framework is used to give the
 * member/owner permission to the given project to the users.
 */
class IDF_Form_MembersConf extends Pluf_Form
{
    public $project = null;

    public function initFields($extra=array())
    {
        $this->project = $extra['project'];

        $this->fields['owners'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project owners'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array('rows' => 5,
                                                                    'cols' => 40),
                                            ));
        $this->fields['members'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project members'),
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));
    }

    public function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        self::updateMemberships($this->project, $this->cleaned_data);
        $this->project->membershipsUpdated();
    }

    public function clean_owners()
    {
        return self::checkBadLogins($this->cleaned_data['owners']);
    }

    public function clean_members()
    {
        return self::checkBadLogins($this->cleaned_data['members']);
    }

    /**
     * From the input, find the bad logins.
     *
     * @throws Pluf_Form_Invalid exception when bad logins are found
     * @param string Comma, new line delimited list of logins
     * @return string Comma, new line delimited list of logins
     */
    public static function checkBadLogins($logins)
    {
        $bad = array();
        foreach (preg_split("/\015\012|\015|\012|\,/", $logins, -1, PREG_SPLIT_NO_EMPTY) as $login) {
            $sql = new Pluf_SQL('login=%s', array(trim($login)));
            try {
                $user = Pluf::factory('Pluf_User')->getOne(array('filter'=>$sql->gen()));
                if (null == $user) {
                    $bad[] = $login;
                }
            } catch (Exception $e) {
                $bad[] = $login;
            }
        }
        $n = count($bad);
        if ($n) {
            $badlogins = Pluf_esc(implode(', ', $bad));
            throw new Pluf_Form_Invalid(sprintf(_n('The following login is invalid: %s.', 'The following login are invalids: %s.', $n), $badlogins));
        }
        return $logins;
    }

    /**
     * The update of the memberships is done in different places. This
     * avoids duplicating code.
     *
     * @param IDF_Project The project
     * @param array The new memberships data in 'owners' and 'members' keys
     */
    public static function updateMemberships($project, $cleaned_data)
    {
        // remove all the permissions
        $cm = $project->getMembershipData();
        $def = array('owners' => Pluf_Permission::getFromString('IDF.project-owner'),
                     'members' => Pluf_Permission::getFromString('IDF.project-member'));
        $guser = new Pluf_User();
        foreach ($def as $key=>$perm) {
            foreach ($cm[$key] as $user) {
                Pluf_RowPermission::remove($user, $project, $perm);
            }
            foreach (preg_split("/\015\012|\015|\012|\,/", $cleaned_data[$key], -1, PREG_SPLIT_NO_EMPTY) as $login) {
                $sql = new Pluf_SQL('login=%s', array(trim($login)));
                $users = $guser->getList(array('filter'=>$sql->gen()));
                if ($users->count() == 1) {
                    Pluf_RowPermission::add($users[0], $project, $perm);
                }
            }
        }
    }
}


