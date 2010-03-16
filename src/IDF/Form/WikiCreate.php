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
 * Create a new documentation page.
 *
 * This create a new page and the corresponding revision.
 *
 */
class IDF_Form_WikiCreate extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $show_full = false;

    public function initFields($extra=array())
    {
        $initial = __('# Introduction

Add your content here.


# Details

Add your content here. Format your content with:

* Text in **bold** or *italic*.
* Headings, paragraphs, and lists.
* Links to other [[WikiPage]].
');
        $this->user = $extra['user'];
        $this->project = $extra['project'];
        if ($this->user->hasPerm('IDF.project-owner', $this->project)
            or $this->user->hasPerm('IDF.project-member', $this->project)) {
            $this->show_full = true;
        }
        $initname = (!empty($extra['name'])) ? $extra['name'] : __('PageName');
        $this->fields['title'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Page title'),
                                            'initial' => $initname,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            'help_text' => __('The page name must contains only letters, digits and the dash (-) character.'),
                                            ));
        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Description'),
                                            'help_text' => __('This one line description is displayed in the list of pages.'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Content'),
                                            'initial' => $initial,
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 68,
                                                       'rows' => 26,
                                                                    ),
                                            ));

        if ($this->show_full) {
            for ($i=1;$i<4;$i++) {
                $this->fields['label'.$i] = new Pluf_Form_Field_Varchar(
                                            array('required' => false,
                                                  'label' => __('Labels'),
                                                  'initial' => '',
                                                  'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                                  ));
            }
        }
    }

    public function clean_title()
    {
        $title = $this->cleaned_data['title'];
        if (preg_match('/[^a-zA-Z0-9\-]/', $title)) {
            throw new Pluf_Form_Invalid(__('The title contains invalid characters.'));
        }
        $sql = new Pluf_SQL('project=%s AND title=%s', 
                            array($this->project->id, $title));
        $pages = Pluf::factory('IDF_WikiPage')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() > 0) {
            throw new Pluf_Form_Invalid(__('A page with this title already exists.'));
        }
        return $title;
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        if (!$this->show_full) {
            return $this->cleaned_data;
        }
        $conf = new IDF_Conf();
        $conf->setProject($this->project);
        $onemax = array();
        foreach (explode(',', $conf->getVal('labels_wiki_one_max', IDF_Form_WikiConf::init_one_max)) as $class) {
            if (trim($class) != '') {
                $onemax[] = mb_strtolower(trim($class));
            }
        }
        $count = array();
        for ($i=1;$i<4;$i++) {
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
                $this->errors['label'.$i][] = sprintf(__('You cannot provide more than label from the %s class to a page.'), $class);
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
        if ($this->show_full) {
            for ($i=1;$i<4;$i++) {
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
        } 
        // Create the page
        $page = new IDF_WikiPage();
        $page->project = $this->project;
        $page->submitter = $this->user;
        $page->summary = trim($this->cleaned_data['summary']);
        $page->title = trim($this->cleaned_data['title']);
        $page->create();
        foreach ($tags as $tag) {
            $page->setAssoc($tag);
        }
        // add the first revision
        $rev = new IDF_WikiRevision();
        $rev->wikipage = $page;
        $rev->content = $this->cleaned_data['content'];
        $rev->submitter = $this->user;
        $rev->summary = __('Initial page creation');
        $rev->create();
        $rev->notify($this->project->getConf());
        return $page;
    }
}
