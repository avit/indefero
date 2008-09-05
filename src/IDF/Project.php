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
 * Base definition of a project.
 *
 * The issue management system can be used to manage several projects
 * at the same time.
 */
class IDF_Project extends Pluf_Model
{
    public $_model = __CLASS__;
    public $_extra_cache = array();
    protected $_pconf = null;

    function init()
    {
        $this->_pconf = null;
        $this->_extra_cache = array();
        $this->_a['table'] = 'idf_projects';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'name' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('name'),
                                  ),
                            'shortname' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 50,
                                  'verbose' => __('short name'),
                                  'help_text' => __('Used in the url to access the project, must be short with only letters and numbers.'),
                                  'unique' => true,
                                  ),
                            'description' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('description'),
                                  'help_text' => __('The description can be extended using the markdown syntax.'),
                                  ),
                                  );
        $this->_a['idx'] = array( );
    }


    /**
     * String representation of the abstract.
     */
    function __toString()
    {
        return $this->name;
    }

    /**
     * String ready for indexation.
     */
    function _toIndex()
    {
        return '';
    }

    
    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    public static function getOr404($shortname)
    {
        $sql = new Pluf_SQL('shortname=%s', array(trim($shortname)));
        $projects = Pluf::factory(__CLASS__)->getList(array('filter' => $sql->gen()));
        if ($projects->count() != 1) {
            throw new Pluf_HTTP_Error404(sprintf(__('Project "%s" not found.'),
                                                 $shortname));
        }
        return $projects[0];
    }

    /**
     * Returns the number of open/closed issues.
     *
     * @param string Status ('open'), 'closed'
     * @param IDF_Tag Subfilter with a label (null)
     * @return int Count
     */
    public function getIssueCountByStatus($status='open', $label=null)
    {
        switch ($status) {
        case 'open':
            $key = 'labels_issue_open';
            $default = IDF_Form_IssueTrackingConf::init_open;
            break;
        case 'closed':
        default:
            $key = 'labels_issue_closed';
            $default = IDF_Form_IssueTrackingConf::init_closed;
            break;
        }
        $tags = array();
        foreach ($this->getTagsFromConfig($key, $default, 'Status') as $tag) {
            $tags[] = (int)$tag->id;
        }
        if (count($tags) == 0) return array();
        $sql = new Pluf_SQL(sprintf('project=%%s AND status IN (%s)', implode(', ', $tags)), array($this->id));
        if (!is_null($label)) {
            $sql2 = new Pluf_SQL('idf_tag_id=%s', array($label->id));
            $sql->SAnd($sql2);
        }
        $params = array('filter' => $sql->gen());
        if (!is_null($label)) { $params['view'] = 'join_tags'; }
        $gissue = new IDF_Issue();
        return $gissue->getCount($params);
    }

    /**
     * Get the open/closed tag ids as they are often used when doing
     * listings.
     *
     * As this can be often used, the info are cached.
     *
     * @param string Status ('open') or 'closed'
     * @param bool Force cache refresh (false)
     * @return array Ids of the open/closed tags
     */
    public function getTagIdsByStatus($status='open', $cache_refresh=false)
    {
        if (!$cache_refresh 
            and isset($this->_extra_cache['getTagIdsByStatus-'.$status])) {
            return $this->_extra_cache['getTagIdsByStatus-'.$status];
        }
        switch ($status) {
        case 'open':
            $key = 'labels_issue_open';
            $default = IDF_Form_IssueTrackingConf::init_open;
            break;
        case 'closed':
        default:
            $key = 'labels_issue_closed';
            $default = IDF_Form_IssueTrackingConf::init_closed;
            break;
        }
        $tags = array();
        foreach ($this->getTagsFromConfig($key, $default, 'Status') as $tag) { 
            $tags[] = (int) $tag->id;
        }
        $this->_extra_cache['getTagIdsByStatus-'.$status] = $tags;
        return $tags;
    }

    /**
     * Convert the definition of tags in the configuration into the
     * corresponding list of tags.
     *
     * @param string Configuration key where the tag is.
     * @param string Default config if nothing in the db.
     * @param string Default class.
     * @return array List of tags
     */
    public function getTagsFromConfig($cfg_key, $default, $dclass='Other')
    {
        $conf = $this->getConf();
        $tags = array();
        foreach (preg_split("/\015\012|\015|\012/", $conf->getVal($cfg_key, $default), -1, PREG_SPLIT_NO_EMPTY) as $s) {
            $_s = split('=', $s, 2);
            $v = trim($_s[0]);
            $_v = split(':', $v, 2);
            if (count($_v) > 1) {
                $class = trim($_v[0]);
                $name = trim($_v[1]);
            } else {
                $name = trim($_s[0]);
                $class = $dclass;
            }
            $tags[] = IDF_Tag::add($name, $this, $class);
        }
        return $tags;
    }

    /**
     * Return membership data.
     *
     * The array has 2 keys: 'members' and 'owners'.
     *
     * The list of users is only taken using the row level permission
     * table. That is, if you set a user as administrator, he will
     * have the member and owner rights but will not appear in the
     * lists.
     *
     * @param string Format ('objects'), 'string'.
     * @return mixed Array of Pluf_User or newline separated list of logins.
     */
    public function getMembershipData($fmt='objects')
    {
        $mperm = Pluf_Permission::getFromString('IDF.project-member');
        $operm = Pluf_Permission::getFromString('IDF.project-owner');
        $grow = new Pluf_RowPermission();
        $db =& Pluf::db();
        $false = Pluf_DB_BooleanToDb(false, $db);
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s AND owner_class=%s AND permission=%s AND negative='.$false,
                            array('IDF_Project', $this->id, 'Pluf_User', $operm->id));
        $owners = new Pluf_Template_ContextVars(array());
        foreach ($grow->getList(array('filter' => $sql->gen())) as $row) {
            if ($fmt == 'objects') {
                $owners[] = Pluf::factory('Pluf_User', $row->owner_id);
            } else {
                $owners[] = Pluf::factory('Pluf_User', $row->owner_id)->login;
            }
        }
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s AND owner_class=%s AND permission=%s AND negative='.$false,
                            array('IDF_Project', $this->id, 'Pluf_User', $mperm->id));
        $members = new Pluf_Template_ContextVars(array());
        foreach ($grow->getList(array('filter' => $sql->gen())) as $row) {
            if ($fmt == 'objects') {
                $members[] = Pluf::factory('Pluf_User', $row->owner_id);
            } else {
                $members[] = Pluf::factory('Pluf_User', $row->owner_id)->login;
            }
        }
        if ($fmt == 'objects') {
            return array('members' => $members, 'owners' => $owners);
        } else {
            return array('members' => implode("\n", $members), 
                         'owners' => implode("\n", $owners));
        }
    }

    /**
     * Generate the tag clouds.
     *
     * Return an array of tags sorted class, then name. Each tag get
     * the extra property 'nb_use' for the number of use in the
     * project. For issues, only open issues are used to generate the
     * cloud.
     *
     * @param string ('issues') 'closed_issues' or 'downloads'
     * @return ArrayObject of IDF_Tag
     */
    public function getTagCloud($what='issues')
    {
        $tag_t = Pluf::factory('IDF_Tag')->getSqlTable();
        if ($what == 'issues' or $what == 'closed_issues') {
            $what_t = Pluf::factory('IDF_Issue')->getSqlTable();
            $asso_t = $this->_con->pfx.'idf_issue_idf_tag_assoc';
            if ($what == 'issues') {
                $ostatus = $this->getTagIdsByStatus('open');
            } else {
                $ostatus = $this->getTagIdsByStatus('closed');
            }
            if (count($ostatus) == 0) $ostatus[] = 0;
            $sql = sprintf('SELECT '.$tag_t.'.id AS id, COUNT(*) AS nb_use FROM '.$tag_t.' '."\n".
                      'LEFT JOIN '.$asso_t.' ON idf_tag_id='.$tag_t.'.id '."\n".
                      'LEFT JOIN '.$what_t.' ON idf_issue_id='.$what_t.'.id '."\n".
                      'WHERE idf_tag_id IS NOT NULL AND '.$what_t.'.status IN (%s) AND '.$what_t.'.project='.$this->id.' GROUP BY '.$tag_t.'.id, '.$tag_t.'.class, '.$tag_t.'.name ORDER BY '.$tag_t.'.class ASC, '.$tag_t.'.name ASC',
                      implode(', ', $ostatus));
        } elseif ($what == 'downloads') {
            $dep_ids = IDF_Views_Download::getDeprecatedFilesIds($this);
            $extra = '';
            if (count($dep_ids) and $what == 'downloads') {
                $extra = ' AND idf_upload_id NOT IN ('.implode(', ', $dep_ids).') ';
            }
            if (count($dep_ids) and $what != 'downloads') {
                $extra = ' AND idf_upload_id IN ('.implode(', ', $dep_ids).') ';
            }
            $what_t = Pluf::factory('IDF_Upload')->getSqlTable();
            $asso_t = $this->_con->pfx.'idf_tag_idf_upload_assoc';
            $sql = 'SELECT '.$tag_t.'.id AS id, COUNT(*) AS nb_use FROM '.$tag_t.' '."\n".
                'LEFT JOIN '.$asso_t.' ON idf_tag_id='.$tag_t.'.id '."\n".
                'LEFT JOIN '.$what_t.' ON idf_upload_id='.$what_t.'.id '."\n".
                'WHERE idf_tag_id IS NOT NULL '.$extra.' AND '.$what_t.'.project='.$this->id.' GROUP BY '.$tag_t.'.id, '.$tag_t.'.class, '.$tag_t.'.name ORDER BY '.$tag_t.'.class ASC, '.$tag_t.'.name ASC';
        }
        $tags = array();
        foreach ($this->_con->select($sql) as $idc) {
            $tag = new IDF_Tag($idc['id']);
            $tag->nb_use = $idc['nb_use'];
            $tags[] = $tag;
        }
        return $tags;
    }

    /**
     * Get the remote access url to the repository.
     *
     */
    public function getRemoteAccessUrl()
    {
        $conf = $this->getConf();
        $scm = $conf->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        return call_user_func(array($scms[$scm], 'getRemoteAccessUrl'),
                              $this);
    }

    /**
     * Get the root name of the project scm
     *
     * @return string SCM root
     */
    public function getScmRoot()
    {
        $conf = $this->getConf();
        $roots = array('git' => 'master', 'svn' => 'HEAD');
        $scm = $conf->getVal('scm', 'git');
        return $roots[$scm];
    }

    /**
     * Check that the object belongs to the project or rise a 404
     * error.
     *
     * By convention, all the objects belonging to a project have the
     * 'project' property set, so this is easy to check.
     *
     * @param Pluf_Model 
     */
    public function inOr404($obj)
    {
        if ($obj->project != $this->id) {
            throw new Pluf_HTTP_Error404();
        }
    }

    /**
     * Utility function to get a configuration object.
     *
     * @return IDF_Conf
     */
    public function getConf()
    {
        if ($this->_pconf == null) {
            $this->_pconf = new IDF_Conf();
            $this->_pconf->setProject($this);
        }
        return $this->_pconf;
    }
            
}