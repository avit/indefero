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
 * SVN utils.
 *
 */
class IDF_Svn
{
    public $repo = '';
    public $username = '';
    public $password = '';
    private $assoc = array('dir' => 'tree',
                           'file' => 'blob');


    public function __construct($repo, $username='', $password='')
    {
        $this->repo = $repo;
        $this->username = $username;
        $this->password = $password;
    }


    /**
     * Test a given object hash.
     *
     * @param string Object hash.
     * @return mixed false if not valid or 'blob', 'tree', 'commit'
     */
    public function testHash($rev, $path='')
    {
        // OK if HEAD on /
        if ($rev === 'HEAD' && $path === '') {
            return 'commit';
        }

        // Else, test the path on revision
        $cmd = sprintf('svn info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlInfo = shell_exec($cmd);

        // If exception is thrown, return false
        try {
            $xml = simplexml_load_string($xmlInfo);
        }
        catch (Exception $e) {
            return false;
        }

        // If the entry node does exists, params are wrong
        if (!isset($xml->entry)) {
            return false;
        }

        // Else, enjoy it :)
        return 'commit';
    }


    /**
     * Given a commit hash returns an array of files in it.
     *
     * A file is a class with the following properties:
     *
     * 'perm', 'type', 'size', 'hash', 'file'
     *
     * @param string Commit ('HEAD')
     * @param string Base folder ('')
     * @return array
     */
    public function filesAtCommit($rev='HEAD', $folder='')
    {
        $cmd = sprintf('svn ls --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$folder),
                       escapeshellarg($rev));
        $xmlLs = shell_exec($cmd);
        $xml = simplexml_load_string($xmlLs);

        $res = array();
        foreach ($xml->list->entry as $entry) {
            $file = array();
            $file['type'] = $this->assoc[(String) $entry['kind']];
            $file['file'] = (String) $entry->name;
            $file['fullpath'] = $folder.'/'.((String) $entry->name);
            $file['date'] = gmdate('Y-m-d H:i:s',
                                   strtotime((String) $entry->commit->date));
            $file['rev'] = (String) $entry->commit['revision'];

            // Get commit message
            $currentReposFile = $this->repo.'/'.$folder.'/'.$file['file'];
            $file['log'] = $this->getCommitMessage($currentReposFile, $rev);

            // Get the size if the type is blob
            if ($file['type'] == 'blob') {
                $file['size'] = (String) $entry->size;
            }

            $file['perm'] = '';

            $res[] = (Object) $file;
        }

        return $res;
    }


    /**
     * Get a commit message for given file and revision.
     *
     * @param string File
     * @param string Commit ('HEAD')
     *
     * @return String commit message
     */
    private function getCommitMessage($file, $rev='HEAD')
    {
        $cmd = sprintf('svn log --xml --limit 1 --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($file),
                       escapeshellarg($rev));
        $xmlLog = shell_exec($cmd);
        $xml = simplexml_load_string($xmlLog);
        return (String) $xml->logentry->msg;
    }


    /**
     * Get the file info.
     *
     * @param string File
     * @param string Commit ('HEAD')
     * @return false Information
     */
    public function getFileInfo($totest, $rev='HEAD')
    {
        $cmd = sprintf('svn info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$totest),
                       escapeshellarg($rev));
        $xmlInfo = shell_exec($cmd);
        $xml = simplexml_load_string($xmlInfo);
        $entry = $xml->entry;

        $file = array();
        $file['fullpath'] = $totest;
        $file['hash'] = (String) $entry->repository->uuid;
        $file['type'] = $this->assoc[(String) $entry['kind']];
        $file['file'] = $totest;
        $file['rev'] = (String) $entry->commit['revision'];
        $file['author'] = (String) $entry->author;
        $file['date'] = gmdate('Y-m-d H:i:s', strtotime((String) $entry->commit->date));
        $file['size'] = (String) $entry->size;
        $file['log'] = '';

        return (Object) $file;
    }


    /**
     * Get a blob.
     *
     * @param string Blob hash
     * @return string Raw blob
     */
    public function getBlob($path, $rev)
    {
        $cmd = sprintf('svn cat --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        return shell_exec($cmd);
    }


    /**
     * Get the branches.
     *
     * @return array Branches.
     */
    public function getBranches()
    {
        $res = array('HEAD');
        return $res;
    }


    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD').
     * @return array Changes.
     */
    public function getCommit($rev='HEAD')
    {
        $res = array();
        $cmd = sprintf('svn log --xml -v --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlRes = shell_exec($cmd);
        $xml = simplexml_load_string($xmlRes);

        $res['author'] = (String) $xml->logentry->author;
        $res['date'] = gmdate('Y-m-d H:i:s', strtotime((String) $xml->logentry->date));
        $res['title'] = (String) $xml->logentry->msg;
        $res['commit'] = (String) $xml->logentry['revision'];
        $res['changes'] = $this->getDiff($rev);
        $res['tree'] = '';


        return (Object) $res;
    }

    private function getDiff($rev='HEAD')
    {
        $res = array();
        $cmd = sprintf('svn diff -c %s --username=%s --password=%s %s',
                       escapeshellarg($rev),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo));
        return shell_exec($cmd);
    }


    /**
     * Get latest changes.
     *
     * @param string Commit ('HEAD').
     * @param int Number of changes (10).
     *
     * @return array Changes.
     */
    public function getChangeLog($rev='HEAD', $n=10)
    {
        $res = array();
        $cmd = sprintf('svn log --xml -v --limit %s --username=%s --password=%s %s@%s',
                       escapeshellarg($n),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlRes = shell_exec($cmd);
        $xml = simplexml_load_string($xmlRes);

        $res = array();
        foreach ($xml->logentry as $entry) {
            $log = array();
            $log['author'] = (String) $entry->author;
            $log['date'] = gmdate('Y-m-d H:i:s', strtotime((String) $entry->date));
            $log['title'] = (String) $entry->msg;
            $log['commit'] = (String) $entry['revision'];
            $log['full_message'] = '';

            $res[] = (Object) $log;
        }

        return $res;
    }


    /**
     * Generate the command to create a zip archive at a given commit.
     * Unsupported feature in subversion
     *
     * @param string dummy
     * @param string dummy
     * @return Exception
     */
    public function getArchiveCommand($commit, $prefix='git-repo-dump/')
    {
        throw new Exception(('Unsupported feature.'));
    }


    /**
     * Get additionnals properties on path and revision
     *
     * @param string File
     * @param string Commit ('HEAD')
     * @return array
     */
    public function getProperties($rev, $path='')
    {
        $res = array();
        $cmd = sprintf('svn proplist --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlProps = shell_exec($cmd);
        $props = simplexml_load_string($xmlProps);

        // No properties, returns an empty array
        if (!isset($props->target)) {
            return $res;
        }

        // Get the value of each property
        foreach ($props->target->property as $prop) {
            $key = (String) $prop['name'];
            $res[$key] = $this->getProperty($key, $rev, $path);
        }

        return $res;
    }


    /**
     * Get a specific additionnal property on path and revision
     *
     * @param string Property
     * @param string File
     * @param string Commit ('HEAD')
     * @return string the property value
     */
    private function getProperty($property, $rev, $path='')
    {
        $res = array();
        $cmd = sprintf('svn propget --xml %s --username=%s --password=%s %s@%s',
                       escapeshellarg($property),
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo.'/'.$path),
                       escapeshellarg($rev));
        $xmlProp = shell_exec($cmd);
        $prop = simplexml_load_string($xmlProp);

        return (String) $prop->target->property;
    }


    /**
     * Get the number of the last commit in the repository.
     *
     * @param string Commit ('HEAD').
     *
     * @return String last number commit
     */
    public function getLastCommit($rev='HEAD')
    {
        $xmlInfo = '';
        $cmd = sprintf('svn info --xml --username=%s --password=%s %s@%s',
                       escapeshellarg($this->username),
                       escapeshellarg($this->password),
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $xmlInfo = shell_exec($cmd);

        $xml = simplexml_load_string($xmlInfo);
        return (String) $xml->entry->commit['revision'];
    }
}

