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
 * Delete a project.
 *
 * It is also removing the SCM files, so handle with care.
 *
 */
class IDF_Form_Admin_ProjectDelete extends Pluf_Form
{
    public $project = null;

    public function initFields($extra=array())
    {
        $this->project = $extra['project'];
        $this->user = $extra['user'];
        $this->fields['code'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Confirmation code'),
                                            'initial' => '',
                                            ));
        $this->fields['agree'] = new Pluf_Form_Field_Boolean(
                                      array('required' => true,
                                            'label' => __('I have made a backup of all the important data of this project.'),
                                            'initial' => '',
                                            ));
    }

    public function clean_code()
    {
        $code = $this->cleaned_data['code'];
        if ($code != $this->getCode()) {
            throw new Pluf_Form_Invalid(__('The confirmation code does not match. Please provide a valid confirmation code to delete the project.'));
        }
        return $code;
    }

    public function clean_agree()
    {
        if (!$this->cleaned_data['agree']) {
            throw new Pluf_Form_Invalid(__('Sorry, you really need to backup your data before deletion.'));
        }
        return $this->cleaned_data['agree'];
    }

    public function getCode()
    {
        return substr(md5(Pluf::f('secret_key').$this->user->id.'.'.$this->project->id),
                      0, 8);
    }


    public function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        // So, we drop the project, it will cascade and delete all the
        // elements of the project. For large projects, this may use
        // quite some memory.
        $this->project->delete();
        return true;
    }
}


