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

Pluf::loadFunction('Pluf_Text_MarkDown_parse');

/**
 * Make the links to issues and commits.
 */
class IDF_Template_Markdown extends Pluf_Template_Tag
{
    private $project = null;
    private $request = null;
    private $scm = null;

    function start($text, $request)
    {
        $this->project = $request->project;
        $this->request = $request;
        // Replace like in the issue text
        $tag = new IDF_Template_IssueComment();
        $text = $tag->start($text, $request, false, false, false, false);
        // Replace [[PageName]] with corresponding link to the page.
        // if not the right to see the
        $text = preg_replace_callback('#\[\[([A-Za-z0-9\-]+)\]\]#im',
                                      array($this, 'callbackWikiPage'), 
                                      $text);
        $filter = new IDF_Template_MarkdownPrefilter();
        echo $filter->go(Pluf_Text_MarkDown_parse($text));
    }

    function callbackWikiPage($m)
    {
        $sql = new Pluf_SQL('project=%s AND title=%s', 
                            array($this->project->id, $m[1]));
        $pages = Pluf::factory('IDF_WikiPage')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1 and !$this->request->rights['hasWikiAccess']) {
            return $m[0];
        }
        if ($pages->count() != 1 and $this->request->rights['hasWikiAccess']
            and !$this->request->user->isAnonymous()) {
            return '<img style="vertical-align: text-bottom;" alt=" " src="'.Pluf::f('url_media').'/idf/img/add.png" /><a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::create', array($this->project->shortname), array('name'=>$m[1])).'" title="'.__('Create this documentation page').'">'.$m[1].'</a>';
        }
        if (!$this->request->rights['hasWikiAccess'] or $pages->count() == 0) {
            return $m[1];
        }
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', array($this->project->shortname, $pages[0]->title)).'" title="'.Pluf_esc($pages[0]->summary).'">'.$m[1].'</a>';
    }
}

