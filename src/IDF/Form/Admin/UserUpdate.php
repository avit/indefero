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
 * Update user's details.
 */
class IDF_Form_Admin_UserUpdate  extends Pluf_Form
{
    public $user = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->fields['first_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('First name'),
                                            'initial' => $this->user->first_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['last_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Last name'),
                                            'initial' => $this->user->last_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                            ));

        $this->fields['email'] = new Pluf_Form_Field_Email(
                                      array('required' => true,
                                            'label' => __('Email'),
                                            'initial' => $this->user->email,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                            ));

        $this->fields['language'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Language'),
                                            'initial' => $this->user->language,
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            'widget_attrs' => array(
                                                       'choices' => 
                                                       Pluf_L10n::getInstalledLanguages()
                                                                    ),
                                            ));

        $this->fields['password'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'help_text' => Pluf_Template::markSafe(__('Leave blank if you do not want to change the password.').'<br />'.__('The password must be hard for other people to find it, but easy for the user to remember.')),
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['password2'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Confirm password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $attrs = ($extra['request']->user->id == $this->user->id) ?
            array('readonly' => 'readonly') : array();
        $this->fields['active'] = new Pluf_Form_Field_Boolean(
                    array('required' => false,
                          'label' => __('Active'),
                          'initial' => $this->user->active,
                          'widget' => 'Pluf_Form_Widget_CheckboxInput',
                          'widget_attrs' => $attrs,
                          'help_text' => __('If the user is not getting the confirmation email or is abusing the system, you can directly enable or disable his account here.'),
                          ));
    }


    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        unset($this->cleaned_data['password2']);
        $update_pass = false;
        if (strlen($this->cleaned_data['password']) == 0) {
            unset($this->cleaned_data['password']);
        } else {
            $update_pass = true;
        }
        $this->user->setFromFormData($this->cleaned_data);
        if ($commit) {
            $this->user->update();
            if ($update_pass) {
                /**
                 * [signal]
                 *
                 * Pluf_User::passwordUpdated
                 *
                 * [sender]
                 *
                 * IDF_Form_UserAccount
                 *
                 * [description]
                 *
                 * This signal is sent when the user updated his
                 * password from his account page.
                 *
                 * [parameters]
                 *
                 * array('user' => $user)
                 *
                 */
                $params = array('user' => $this->user);
                Pluf_Signal::send('Pluf_User::passwordUpdated',
                                  'IDF_Form_Admin_UserUpdate', $params);
            }
        }
        return $this->user;
    }

    function clean_last_name()
    {
        $last_name = trim($this->cleaned_data['last_name']);
        if ($last_name == mb_strtoupper($last_name)) {
            return mb_convert_case(mb_strtolower($last_name), 
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $last_name;
    }

    function clean_first_name()
    {
        $first_name = trim($this->cleaned_data['first_name']);
        if ($first_name == mb_strtoupper($first_name)) {
            return mb_convert_case(mb_strtolower($first_name), 
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $first_name;
    }

    function clean_email()
    {
        $email = mb_strtolower(trim($this->cleaned_data['email']));
        $sql = new Pluf_SQL('email=%s AND id!=%s',
                            array($email, $this->user->id));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() > 0) {
            throw new Pluf_Form_Invalid(__('A user with this email already exists, please provide another email address.'));
        }
        return $email;
    }

    /**
     * Check to see if the 2 passwords are the same.
     */
    public function clean()
    {
        if (!isset($this->errors['password']) 
            && !isset($this->errors['password2'])) {
            $password1 = $this->cleaned_data['password'];
            $password2 = $this->cleaned_data['password2'];
            if ($password1 != $password2) {
                throw new Pluf_Form_Invalid(__('The passwords do not match. Please give them again.'));
            }
        }
        return $this->cleaned_data;
    }
}
