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
 * Reset the password of a user.
 *
 */
class IDF_Form_PasswordReset extends Pluf_Form
{
    protected $user = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->fields['key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your verification key'),
                                            'initial' => $extra['key'],
                                            'widget' => 'Pluf_Form_Widget_HiddenInput',
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
     * Check the passwords.
     */
    public function clean()
    {
        if ($this->cleaned_data['password'] != $this->cleaned_data['password2']) {
            throw new Pluf_Form_Invalid(__('The two passwords must be the same.'));
        }
        if (!$this->user->active) {
            throw new Pluf_Form_Invalid(__('This account is not active. Please contact the forge administrator to activate it.'));
        }
        return $this->cleaned_data;
    }


    /**
     * Validate the key.
     */
    public function clean_key()
    {
        $this->cleaned_data['key'] = trim($this->cleaned_data['key']);
        $error = __('We are sorry but this validation key is not valid. Maybe you should directly copy/paste it from your validation email.');
        if (false === ($cres=IDF_Form_PasswordInputKey::checkKeyHash($this->cleaned_data['key']))) {
            throw new Pluf_Form_Invalid($error);
        }
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s AND id=%s', 
                            array($cres[0], $cres[1]));
        if ($guser->getCount(array('filter' => $sql->gen())) != 1) {
            throw new Pluf_Form_Invalid($error);
        }
        if ((time() - $cres[2]) > 86400) {
            throw new Pluf_Form_Invalid(__('Sorry, but this verification key has expired, please restart the password recovery sequence. For security reasons, the verification key is only valid 24h.'));
        }
        return $this->cleaned_data['key'];
    }

    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save an invalid form.'));
        }
        $this->user->setFromFormData($this->cleaned_data);
        if ($commit) {
            $this->user->update();
            /**
             * [signal]
             *
             * Pluf_User::passwordUpdated
             *
             * [sender]
             *
             * IDF_Form_PasswordReset
             *
             * [description]
             *
             * This signal is sent when the user reset his
             * password from the password recovery page.
             *
             * [parameters]
             *
             * array('user' => $user)
             *
             */
            $params = array('user' => $this->user);
            Pluf_Signal::send('Pluf_User::passwordUpdated',
                              'IDF_Form_PasswordReset', $params);
        }
        return $this->user;
    }
}
