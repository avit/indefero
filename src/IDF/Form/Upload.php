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
 * Upload a file for download.
 *
 */
class IDF_Form_Upload extends Pluf_Form
{
    public $user = null;
    public $project = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->project = $extra['project'];

        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Summary'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        $this->fields['changelog'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Description'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 13,
                                                                    ),
                                            ));
        $this->fields['file'] = new Pluf_Form_Field_File(
                                      array('required' => true,
                                            'label' => __('File'),
                                            'initial' => '',
                                            'max_size' => Pluf::f('max_upload_size', 2097152),
                                            'move_function_params' => array('upload_path' => Pluf::f('upload_path').'/'.$this->project->shortname.'/files',
                                                                            'upload_path_create' => true,
                                                                            'upload_overwrite' => false),

                                            ));
        for ($i=1;$i<7;$i++) {
            $this->fields['label'.$i] = new Pluf_Form_Field_Varchar(
                               array('required' => false,
                                     'label' => __('Labels'),
                                     'widget_attrs' => array(
                                             'maxlength' => 50,
                                             'size' => 20,
                                                             ),
                                     ));
        }
    }


    public function clean_file()
    {
        $extra = strtolower(implode('|', explode(' ', Pluf::f('idf_extra_upload_ext'))));
        if (strlen($extra)) $extra .= '|';
        if (!preg_match('/\.('.$extra.'png|jpg|jpeg|gif|bmp|psd|tif|aiff|asf|avi|bz2|css|doc|eps|gz|jar|mdtext|mid|mov|mp3|mpg|ogg|pdf|ppt|ps|qt|ra|ram|rm|rtf|sdd|sdw|sit|sxi|sxw|swf|tgz|txt|wav|xls|xml|war|wmv|zip)$/i', $this->cleaned_data['file'])) {
            @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->cleaned_data['file']);
            throw new Pluf_Form_Invalid(__('For security reason, you cannot upload a file with this extension.'));
        }
        return $this->cleaned_data['file'];
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        $conf = new IDF_Conf();
        $conf->setProject($this->project);
        $onemax = array();
        foreach (explode(',', $conf->getVal('labels_download_one_max', IDF_Form_UploadConf::init_one_max)) as $class) {
            if (trim($class) != '') {
                $onemax[] = mb_strtolower(trim($class));
            }
        }
        $count = array();
        for ($i=1;$i<7;$i++) {
            $this->cleaned_data['label'.$i] = trim($this->cleaned_data['label'.$i]);
            if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                list($class, $name) = array(mb_strtolower(trim($class)), 
                                            trim($name));
            } else {
                $class = 'other';
                $name = $this->cleaned_data['label'.$i];
            }
            if (!isset($count[$class])) $count[$class] = 1;
            else $count[$class] += 1;
            if (in_array($class, $onemax) and $count[$class] > 1) {
                if (!isset($this->errors['label'.$i])) $this->errors['label'.$i] = array();
                $this->errors['label'.$i][] = sprintf(__('You cannot provide more than label from the %s class to an issue.'), $class);
                throw new Pluf_Form_Invalid(__('You provided an invalid label.'));
            }
        }
        return $this->cleaned_data;
    }

    /**
     * If we have uploaded a file, but the form failed remove it.
     *
     */
    function failed()
    {
        if (!empty($this->cleaned_data['file']) 
            and file_exists(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->cleaned_data['file'])) {
            @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->cleaned_data['file']);
        }
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
        // Add a tag for each label
        $tags = array();
        for ($i=1;$i<7;$i++) {
            if (strlen($this->cleaned_data['label'.$i]) > 0) {
                if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                    list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                    list($class, $name) = array(trim($class), trim($name));
                } else {
                    $class = 'Other';
                    $name = trim($this->cleaned_data['label'.$i]);
                }
                $tags[] = IDF_Tag::add($name, $this->project, $class);
            }
        }
        // Create the upload
        $upload = new IDF_Upload();
        $upload->project = $this->project;
        $upload->submitter = $this->user;
        $upload->summary = trim($this->cleaned_data['summary']);
        $upload->changelog = trim($this->cleaned_data['changelog']);
        $upload->file = $this->cleaned_data['file'];
        $upload->filesize = filesize(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->cleaned_data['file']);
        $upload->downloads = 0;
        $upload->create();
        foreach ($tags as $tag) {
            $upload->setAssoc($tag);
        }
        // Send the notification
        $upload->notify($this->project->getConf());
        /**
         * [signal]
         *
         * IDF_Upload::create
         *
         * [sender]
         *
         * IDF_Form_Upload
         *
         * [description]
         *
         * This signal allows an application to perform a set of tasks
         * just after the upload of a file and after the notification run.
         *
         * [parameters]
         *
         * array('upload' => $upload);
         *
         */
        $params = array('upload' => $upload);
        Pluf_Signal::send('IDF_Upload::create', 'IDF_Form_Upload',
                          $params);
        return $upload;
    }
}

