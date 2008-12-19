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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');

/**
 * Confirmation of the form.
 *
 */
class IDF_Form_RegisterConfirmation extends Pluf_Form
{
    public $_user = null;

    public function initFields($extra=array())
    {
        $this->_user = $extra['user'];

        $this->fields['key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your confirmation key'),
                                            'initial' => $extra['key'],
                                            'widget' => 'Pluf_Form_Widget_HiddenInput',
                                            'widget_attrs' => array(
                                                       'readonly' => 'readonly',
                                                                    ),

                                            ));
        $this->fields['first_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('First name'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['last_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Last name'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['password'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'help_text' => __('Your password must be hard for other people to find it, but easy for you to remember.'),
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['password2'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Confirm your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        

        
    }

    /**
     * Just a simple control.
     */
    public function clean_key()
    {
        $this->cleaned_data['key'] = trim($this->cleaned_data['key']);
        $error = __('We are sorry but this confirmation key is not valid. Maybe you should directly copy/paste it from your confirmation email.');
        if (false === ($email_id=IDF_Form_RegisterInputKey::checkKeyHash($this->cleaned_data['key']))) {
            throw new Pluf_Form_Invalid($error);
        }
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s AND id=%s', $email_id);
        $users = $guser->getList(array('filter' => $sql->gen()));
        if ($users->count() != 1) {
            throw new Pluf_Form_Invalid($error);
        }
        if ($users[0]->active) {
            throw new Pluf_Form_Invalid(__('This account has already been confirmed. Maybe should you try to recover your password using the help link.'));
        }
        $this->_user_id = $email_id[1];
        return $this->cleaned_data['key'];
    }

    /**
     * Check the passwords.
     */
    public function clean()
    {
        if ($this->cleaned_data['password'] != $this->cleaned_data['password2']) {
            throw new Pluf_Form_Invalid(__('The two passwords must be the same.'));
        }
        return $this->cleaned_data;
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
            throw new Exception(__('Cannot save an invalid form.'));
        }
        $this->_user->setFromFormData($this->cleaned_data);
        $this->_user->active = true;
        $this->_user->administrator = false;
        $this->_user->staff = false;
        if ($commit) {
            $this->_user->update();
            /**
             * [signal]
             *
             * Pluf_User::passwordUpdated
             *
             * [sender]
             *
             * IDF_Form_RegisterConfirmation
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
            $params = array('user' => $this->_user);
            Pluf_Signal::send('Pluf_User::passwordUpdated',
                              'IDF_Form_RegisterConfirmation', $params);
        }
        return $this->_user;
    }
}
