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
Pluf::loadFunction('Pluf_Shortcuts_RenderToResponse');
Pluf::loadFunction('Pluf_Shortcuts_GetObjectOr404');
Pluf::loadFunction('Pluf_Shortcuts_GetFormForModel');

/**
 * Documentation pages views.
 */
class IDF_Views_Wiki
{
    /**
     * View list of issues for a given project.
     */
    public $index_precond = array('IDF_Precondition::accessWiki');
    public function index($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Documentation'), (string) $prj);
        // Paginator to paginate the pages
        $pag = new Pluf_Paginator(new IDF_WikiPage());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the documentation pages.');
        $pag->action = array('IDF_Views_Wiki::index', array($prj->shortname));
        $pag->edit_action = array('IDF_Views_Wiki::view', 'shortname', 'title');
        $sql = 'project=%s';
        $ptags = self::getWikiTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $ids = self::getDeprecatedPagesIds($prj, $dtag);
        if (count($ids)) {
            $sql .= ' AND id NOT IN ('.implode(',', $ids).')';
        }
        $pag->forced_where = new Pluf_SQL($sql, array($prj->id));
        $list_display = array(
             'title' => __('Page Title'),
             array('summary', 'IDF_Views_Wiki_SummaryAndLabels', __('Summary')),
             array('modif_dtime', 'Pluf_Paginator_DateYMD', __('Updated')),
                              );
        $pag->configure($list_display, array(), array('title', 'modif_dtime'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No documentation pages were found.');
        $pag->sort_order = array('title', 'ASC');
        $pag->setFromRequest($request);
        //$tags = $prj->getTagCloud('downloads');
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'pages' => $pag,
                                                     //'tags' => $tags,
                                                     'deprecated' => count($ids),
                                                     'dlabel' => $dtag,
                                                     ),
                                               $request);
    }

    /**
     * Create a new documentation page.
     */
    public $create_precond = array('IDF_Precondition::accessWiki',
                                   'Pluf_Precondition::loginRequired');
    public function create($request, $match)
    {
        $prj = $request->project;
        $title = __('New Page');
        $preview = false;
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiCreate($request->POST,
                                            array('project' => $prj,
                                                  'user' => $request->user
                                                  ));
            if ($form->isValid() and !isset($request->POST['preview'])) {
                $page = $form->save();
                $urlpage = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', 
                                                    array($prj->shortname, $page->title));
                $request->user->setMessage(sprintf(__('The page <a href="%s">%s</a> has been created.'), $urlpage, Pluf_esc($page->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::index', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            } elseif (isset($request->POST['preview'])) {
                $preview = $request->POST['content'];
            }
        } else {
            $form = new IDF_Form_WikiCreate(null,
                                        array('project' => $prj,
                                              'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/create.html',
                                               array(
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'preview' => $preview,
                                                     ),
                                               $request);
    }

    /**
     * View a documentation page.
     */
    public $view_precond = array('IDF_Precondition::accessWiki');
    public function view($request, $match)
    {
        $prj = $request->project;
        // Find the page
        $sql = new Pluf_SQL('project=%s AND title=%s', 
                            array($prj->id, $match[2]));
        $pages = Pluf::factory('IDF_WikiPage')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1) {
            throw new Pluf_HTTP_Error404($request);
        }
        $page = $pages[0];
        $oldrev = false;
        // We grab the old revision if requested.
        if (isset($request->GET['rev']) and preg_match('/^[0-9]+$/', $request->GET['rev'])) {
            $oldrev = Pluf_Shortcuts_GetObjectOr404('IDF_WikiRevision',
                                                    $request->GET['rev']);
            if ($oldrev->wikipage != $page->id) {
                throw new Pluf_HTTP_Error404($request);
            }
        }
        $title = $page->title;
        $revision = $page->get_current_revision();
        $db = $page->getDbConnection();
        $false = Pluf_DB_BooleanToDb(false, $db);
        $revs = $page->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/view.html',
                                               array(
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'oldrev' => $oldrev,
                                                     'rev' => $revision,
                                                     'revs' => $revs,
                                                     'tags' => $page->get_tags_list(),
                                                     ),
                                               $request);
    }

    /**
     * View a documentation page.
     */
    public $update_precond = array('IDF_Precondition::accessWiki',
                                   'Pluf_Precondition::loginRequired');
    public function update($request, $match)
    {
        $prj = $request->project;
        // Find the page
        $sql = new Pluf_SQL('project=%s AND title=%s', 
                            array($prj->id, $match[2]));
        $pages = Pluf::factory('IDF_WikiPage')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1) {
            throw new Pluf_HTTP_Error404($request);
        }
        $page = $pages[0];
        $title = sprintf(__('Update %s'), $page->title);
        $revision = $page->get_current_revision();
        $preview = false;
        $params = array('project' => $prj,
                        'user' => $request->user,
                        'page' => $page);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiUpdate($request->POST, $params);
            if ($form->isValid() and !isset($request->POST['preview'])) {
                $page = $form->save();
                $urlpage = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', 
                                                    array($prj->shortname, $page->title));
                $request->user->setMessage(sprintf(__('The page <a href="%s">%s</a> has been updated.'), $urlpage, Pluf_esc($page->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::index', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            } elseif (isset($request->POST['preview'])) {
                $preview = $request->POST['content'];
            }
        } else {
                
            $form = new IDF_Form_WikiUpdate(null, $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/update.html',
                                               array(
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'rev' => $revision,
                                                     'form' => $form,
                                                     'preview' => $preview,
                                                     ),
                                               $request);
    }

    /**
     * Get the wiki tags.
     *
     * @param IDF_Project
     * @return ArrayObject The tags
     */
    public static function getWikiTags($project)
    {
        return $project->getTagsFromConfig('labels_wiki_predefined',
                                           IDF_Form_WikiConf::init_predefined);

    }

    /**
     * Get deprecated page ids.
     *
     * @param IDF_Project
     * @param IDF_Tag Deprecated tag (null)
     * @return array Ids of the deprecated pages.
     */
    public static function getDeprecatedPagesIds($project, $dtag=null)
    {
        if (is_null($dtag)) {
            $ptags = self::getDownloadTags($project);
            $dtag = array_pop($ptags); // The last tag is the deprecated tag
        }
        $sql = new Pluf_SQL('project=%s AND idf_tag_id=%s', array($project->id,
                                                                  $dtag->id));
        $ids = array();
        foreach (Pluf::factory('IDF_WikiPage')->getList(array('filter' => $sql->gen(), 'view' => 'join_tags'))
                 as $file) {
            $ids[] = (int) $file->id;
        }
        return $ids;
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays($project)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $st = preg_split("/\015\012|\015|\012/", 
                         $conf->getVal('labels_wiki_predefined', IDF_Form_UploadConf::init_predefined), -1, PREG_SPLIT_NO_EMPTY);
        $auto = '';
        foreach ($st as $s) {
            $v = '';
            $d = '';
            $_s = split('=', $s, 2);
            if (count($_s) > 1) {
                $v = trim($_s[0]);
                $d = trim($_s[1]);
            } else {
                $v = trim($_s[0]);
            }
            $auto .= sprintf('{ name: "%s", to: "%s" }, ',
                             Pluf_esc($d), Pluf_esc($v));
        }
        return substr($auto, 0, -1);
    }

}

/**
 * Display the summary of a page, then on a new line, display the
 * list of labels.
 */
function IDF_Views_Wiki_SummaryAndLabels($field, $page, $extra='')
{
    $tags = array();
    foreach ($page->get_tags_list() as $tag) {
        $tags[] = Pluf_esc((string) $tag);
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note label">'.implode(', ', $tags).'</span>';
    }
    return Pluf_esc($page->summary).$out;
}
