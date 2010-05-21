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
 * Download's views.
 *
 * - List all the files.
 * - Upload a file.
 * - See the details of a file.
 */
class IDF_Views_Download
{
    /**
     * List the files available for download.
     */
    public $index_precond = array('IDF_Precondition::accessDownloads');
    public function index($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Downloads'), (string) $prj);
        // Paginator to paginate the files to download.
        $pag = new Pluf_Paginator(new IDF_Upload());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname);
        $pag->summary = __('This table shows the files to download.');
        $pag->action = array('IDF_Views_Download::index', array($prj->shortname));
        $pag->edit_action = array('IDF_Views_Download::view', 'shortname', 'id');
        $sql = 'project=%s';
        $ptags = self::getDownloadTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $ids = self::getDeprecatedFilesIds($prj);
        if (count($ids)) {
            $sql .= ' AND id NOT IN ('.implode(',', $ids).')';
        }
        $pag->forced_where = new Pluf_SQL($sql, array($prj->id));
            
        $list_display = array(
             'file' => __('File'),
             array('summary', 'IDF_Views_Download_SummaryAndLabels', __('Summary')),
             array('filesize', 'IDF_Views_Download_Size', __('Size')),
             array('creation_dtime', 'Pluf_Paginator_DateYMD', __('Uploaded')),
                              );
        $pag->configure($list_display, array(), array('file', 'filesize', 'creation_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No downloads were found.');
        $pag->sort_order = array('creation_dtime', 'DESC');
        $pag->setFromRequest($request);
        $tags = $prj->getTagCloud('downloads');
        return Pluf_Shortcuts_RenderToResponse('idf/downloads/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'downloads' => $pag,
                                                     'tags' => $tags,
                                                     'deprecated' => count($ids),
                                                     'dlabel' => $dtag,
                                                     ),
                                               $request);
        
    }

    /**
     * View details of a file.
     */
    public $view_precond = array('IDF_Precondition::accessDownloads');
    public function view($request, $match)
    {
        $prj = $request->project;
        $upload = Pluf_Shortcuts_GetObjectOr404('IDF_Upload', $match[2]);
        $prj->inOr404($upload);
        $title = sprintf(__('Download %s'), $upload->summary);
        $form = false;
        $ptags = self::getDownloadTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $tags = $upload->get_tags_list();
        $deprecated = Pluf_Model_InArray($dtag, $tags);
        if ($request->method == 'POST' and
            true === IDF_Precondition::projectMemberOrOwner($request)) {
            
            $form = new IDF_Form_UpdateUpload($request->POST,
                                        array('project' => $prj,
                                              'upload' => $upload,
                                              'user' => $request->user));
            if ($form->isValid()) {
                $upload = $form->save();
                $urlfile = Pluf_HTTP_URL_urlForView('IDF_Views_Download::view', 
                                                    array($prj->shortname, $upload->id));
                $request->user->setMessage(sprintf(__('The file <a href="%1$s">%2$s</a> has been updated.'), $urlfile, Pluf_esc($upload->file)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Download::index', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } elseif (true === IDF_Precondition::projectMemberOrOwner($request)) {
            $form = new IDF_Form_UpdateUpload(null,
                                              array('upload' => $upload,
                                                    'project' => $prj,
                                                    'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/downloads/view.html',
                                               array(
                                                     'file' => $upload,
                                                     'deprecated' => $deprecated,
                                                     'tags' => $tags,
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Delete a file.
     */
    public $delete_precond = array('IDF_Precondition::accessDownloads',
                                   'IDF_Precondition::projectMemberOrOwner');
    public function delete($request, $match)
    {
        $prj = $request->project;
        $upload = Pluf_Shortcuts_GetObjectOr404('IDF_Upload', $match[2]);
        $prj->inOr404($upload);
        $title = sprintf(__('Delete Download %s'), $upload->summary);
        $form = false;
        $ptags = self::getDownloadTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $tags = $upload->get_tags_list();
        $deprecated = Pluf_Model_InArray($dtag, $tags);
        if ($request->method == 'POST') {
            $fname = $upload->file;
            @unlink(Pluf::f('upload_path').'/'.$prj->shortname.'/files/'.$fname);
            /**
             * [signal]
             *
             * IDF_Upload::delete
             *
             * [sender]
             *
             * IDF_Form_UpdateUpload
             *
             * [description]
             *
             * This signal allows an application to perform a set of tasks
             * just before the deletion of the corresponding object in the 
             * database but just after the deletion from the storage.
             *
             * [parameters]
             *
             * array('upload' => $upload);
             *
             */
            $params = array('upload' => $upload);
            Pluf_Signal::send('IDF_Upload::delete', 
                              'IDF_Views_Download', $params);
            $upload->delete();
            $request->user->setMessage(__('The file has been deleted.'));
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Download::index', 
                                            array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/downloads/delete.html',
                                               array(
                                                     'file' => $upload,
                                                     'deprecated' => $deprecated,
                                                     'tags' => $tags,
                                                     'page_title' => $title,
                                                     ),
                                               $request);
    }

    /**
     * Download a file.
     */
    public $download_precond = array('IDF_Precondition::accessDownloads');
    public function download($request, $match)
    {
        $prj = $request->project;
        $upload = Pluf_Shortcuts_GetObjectOr404('IDF_Upload', $match[2]);
        $prj->inOr404($upload);
        $upload->downloads += 1;
        $upload->update();
        return new Pluf_HTTP_Response_Redirect($upload->getAbsoluteUrl($prj));
    }

    /**
     * Submit a new file for download.
     */
    public $submit_precond = array('IDF_Precondition::accessDownloads',
                                   'IDF_Precondition::projectMemberOrOwner');
    public function submit($request, $match)
    {
        $prj = $request->project;
        $title = __('New Download');
        if ($request->method == 'POST') {
            $form = new IDF_Form_Upload(array_merge($request->POST, $request->FILES),
                                        array('project' => $prj,
                                              'user' => $request->user));
            if ($form->isValid()) {
                $upload = $form->save();
                $urlfile = Pluf_HTTP_URL_urlForView('IDF_Views_Download::view', 
                                                    array($prj->shortname, $upload->id));
                $request->user->setMessage(sprintf(__('The <a href="%s">file</a> has been uploaded.'), $urlfile));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Download::index', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Upload(null,
                                        array('project' => $prj,
                                              'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/downloads/submit.html',
                                               array(
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays($project)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $st = preg_split("/\015\012|\015|\012/", 
                         $conf->getVal('labels_download_predefined', IDF_Form_UploadConf::init_predefined), -1, PREG_SPLIT_NO_EMPTY);
        $auto = '';
        foreach ($st as $s) {
            $v = '';
            $d = '';
            $_s = explode('=', $s, 2);
            if (count($_s) > 1) {
                $v = trim($_s[0]);
                $d = trim($_s[1]);
            } else {
                $v = trim($_s[0]);
            }
            $auto .= sprintf('{ name: "%s", to: "%s" }, ',
                             Pluf_esc($d), Pluf_esc($v));
        }
        return substr($auto, 0, -2);
    }

    /**
     * View list of downloads with a given label.
     */
    public $listLabel_precond = array('IDF_Precondition::accessDownloads');
    public function listLabel($request, $match)
    {
        $prj = $request->project;
        $tag = Pluf_Shortcuts_GetObjectOr404('IDF_Tag', $match[2]);
        $prj->inOr404($tag);
        $title = sprintf(__('%1$s Downloads with Label %2$s'), (string) $prj,
                         (string) $tag);
        // Paginator to paginate the downloads
        $ptags = self::getDownloadTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $pag = new Pluf_Paginator(new IDF_Upload());
        $pag->model_view = 'join_tags';
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname);
        $pag->summary = sprintf(__('This table shows the downloads with label %s.'), (string) $tag);
        $pag->forced_where = new Pluf_SQL('project=%s AND idf_tag_id=%s', array($prj->id, $tag->id));
        $pag->action = array('IDF_Views_Download::listLabel', array($prj->shortname, $tag->id));
        $pag->edit_action = array('IDF_Views_Download::view', 'shortname', 'id');
        $list_display = array(
             'file' => __('File'),
             array('summary', 'IDF_Views_Download_SummaryAndLabels', __('Summary')),
             array('filesize', 'IDF_Views_Download_Size', __('Size')),
             array('creation_dtime', 'Pluf_Paginator_DateYMD', __('Uploaded')),
                              );
        $pag->configure($list_display, array(), array('file', 'filesize', 'creation_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No downloads were found.');
        $pag->sort_order = array('creation_dtime', 'DESC');
        $pag->setFromRequest($request);
        $tags = $prj->getTagCloud('downloads');
        return Pluf_Shortcuts_RenderToResponse('idf/downloads/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'label' => $tag,
                                                     'downloads' => $pag,
                                                     'tags' => $tags,
                                                     'dlabel' => $dtag,
                                                     ),
                                               $request);
    }

    /**
     * Get the download tags.
     *
     * @param IDF_Project
     * @return ArrayObject The tags
     */
    public static function getDownloadTags($project)
    {
        return $project->getTagsFromConfig('labels_download_predefined',
                                           IDF_Form_UploadConf::init_predefined);

    }

    /**
     * Get deprecated file ids.
     *
     * @return array Ids of the deprecated files.
     */
    public static function getDeprecatedFilesIds($project)
    {
        $ptags = self::getDownloadTags($project);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $sql = new Pluf_SQL('project=%s AND idf_tag_id=%s', array($project->id,
                                                                  $dtag->id));
        $ids = array();
        foreach (Pluf::factory('IDF_Upload')->getList(array('filter' => $sql->gen(), 'view' => 'join_tags'))
                 as $file) {
            $ids[] = (int) $file->id;
        }
        return $ids;
    }
}

/**
 * Display the summary of a download, then on a new line, display the
 * list of labels.
 *
 * The summary of the download is linking to the download.
 */
function IDF_Views_Download_SummaryAndLabels($field, $down, $extra='')
{
    $tags = array();
    foreach ($down->get_tags_list() as $tag) {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Download::listLabel', 
                                        array($down->shortname, $tag->id));
        $tags[] = sprintf('<a href="%s" class="label">%s</a>', $url, Pluf_esc((string) $tag));
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note">'.implode(', ', $tags).'</span>';
    }
    return Pluf_esc($down->summary).$out;
}

function IDF_Views_Download_Size($field, $down)
{
    return Pluf_Utils::prettySize($down->$field);
}