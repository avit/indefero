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
 * Update a file for download.
 *
 */
class IDF_Form_UpdateUpload extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $upload = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->project = $extra['project'];
        $this->upload = $extra['upload'];

        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Summary'),
                                            'initial' => $this->upload->summary,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        $this->fields['changelog'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Description'),
                                            'initial' => $this->upload->changelog,
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 13,
                                                                    ),
                                            ));
        $tags = $this->upload->get_tags_list();
        for ($i=1;$i<7;$i++) {
            $initial = '';
            if (isset($tags[$i-1])) {
                if ($tags[$i-1]->class != 'Other') {
                    $initial = (string) $tags[$i-1];
                } else {
                    $initial = $tags[$i-1]->name;
                }
            }
            $this->fields['label'.$i] = new Pluf_Form_Field_Varchar(
                               array('required' => false,
                                     'label' => __('Labels'),
                                     'initial' => $initial,
                                     'widget_attrs' => array(
                                             'maxlength' => 50,
                                             'size' => 20,
                                                             ),
                                     ));
        }
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
                $tag = IDF_Tag::add($name, $this->project, $class);
                $tags[] = $tag->id;
            }
        }
        // Create the upload
        $this->upload->summary = trim($this->cleaned_data['summary']);
        $this->upload->changelog = trim($this->cleaned_data['changelog']);
        $this->upload->modif_dtime = gmdate('Y-m-d H:i:s');
        $this->upload->update();
        $this->upload->batchAssoc('IDF_Tag', $tags);
        /**
         * [signal]
         *
         * IDF_Upload::update
         *
         * [sender]
         *
         * IDF_Form_UpdateUpload
         *
         * [description]
         *
         * This signal allows an application to perform a set of tasks
         * just after the update of an uploaded file.
         *
         * [parameters]
         *
         * array('upload' => $upload);
         *
         */
        $params = array('upload' => $this->upload);
        Pluf_Signal::send('IDF_Upload::update', 
                          'IDF_Form_UpdateUpload', $params);
        return $this->upload;
    }
}

