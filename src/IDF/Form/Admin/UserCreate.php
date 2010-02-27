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
 * Allow an admin to create a user.
 */
class IDF_Form_Admin_UserCreate extends Pluf_Form
{
    public $request = null;

    public function initFields($extra=array())
    {
        $this->request = $extra['request'];
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
                                                       'size' => 20,
                                                                    ),
                                            ));

        $this->fields['login'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Login'),
                                            'max_length' => 15,
                                            'min_length' => 3,
                                            'initial' => '',
                                            'help_text' => __('The login must be between 3 and 15 characters long and contains only letters and digits.'),
                                            'widget_attrs' => array(
                                                       'maxlength' => 15,
                                                       'size' => 10,
                                                                    ),
                                            ));

        $this->fields['email'] = new Pluf_Form_Field_Email(
                                      array('required' => true,
                                            'label' => __('Email'),
                                            'initial' => '',
                                            'help_text' => __('Double check the email address as the password is directly sent to the user.'),
                                            ));

        $this->fields['language'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Language'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            'widget_attrs' => array(
                                                       'choices' => 
                                                       Pluf_L10n::getInstalledLanguages()
                                                                    ),
                                            ));

        $this->fields['ssh_key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Add a public SSH key'),
                                            'initial' => '',
                                            'widget_attrs' => array('rows' => 3,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'help_text' => __('Be careful to provide the public key and not the private key!')
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
        $password = Pluf_Utils::getPassword();
        $user = new Pluf_User();
        $user->setFromFormData($this->cleaned_data);
        $user->active = true;
        $user->staff = false;
        $user->administrator = false;
        $user->setPassword($password);
        $user->create();
        /**
         * [signal]
         *
         * Pluf_User::passwordUpdated
         *
         * [sender]
         *
         * IDF_Form_Admin_UserCreate
         *
         * [description]
         *
         * This signal is sent when a user is created
         * by the staff.
         *
         * [parameters]
         *
         * array('user' => $user)
         *
         */
        $params = array('user' => $user);
        Pluf_Signal::send('Pluf_User::passwordUpdated',
                          'IDF_Form_Admin_UserCreate', $params);
        // Create the ssh key as needed
        if ('' !== $this->cleaned_data['ssh_key']) {
            $key = new IDF_Key();
            $key->user = $user;
            $key->content = $this->cleaned_data['ssh_key'];
            $key->create();
        }
        // Send an email to the user with the password
        Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
        $url = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views::login', array(), array(), false);
        $context = new Pluf_Template_Context(
                 array('password' => Pluf_Template::markSafe($password),
                       'user' => $user,
                       'url' => Pluf_Template::markSafe($url),
                       'admin' => $this->request->user,
                       ));
        $tmpl = new Pluf_Template('idf/gadmin/users/createuser-email.txt');
        $text_email = $tmpl->render($context);
        $email = new Pluf_Mail(Pluf::f('from_email'), $user->email,
                               __('Your details to access your forge.'));
        $email->addTextMessage($text_email);
        $email->sendMail();
        return $user;
    }

    function clean_ssh_key()
    {
        return IDF_Form_UserAccount::checkSshKey($this->cleaned_data['ssh_key']);
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
        $this->cleaned_data['email'] = mb_strtolower(trim($this->cleaned_data['email']));
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s', array($this->cleaned_data['email']));
        if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
            throw new Pluf_Form_Invalid(sprintf(__('The email "%s" is already used.'), $this->cleaned_data['email']));
        }
        return $this->cleaned_data['email'];
    }

    public function clean_login()
    {
        $this->cleaned_data['login'] = mb_strtolower(trim($this->cleaned_data['login']));
        if (preg_match('/[^a-z0-9]/', $this->cleaned_data['login'])) {
            throw new Pluf_Form_Invalid(sprintf(__('The login "%s" can only contain letters and digits.'), $this->cleaned_data['login']));
        }
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('login=%s', $this->cleaned_data['login']);
        if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
            throw new Pluf_Form_Invalid(sprintf(__('The login "%s" is already used, please find another one.'), $this->cleaned_data['login']));
        }
        return $this->cleaned_data['login'];
    }
}
