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
n# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


/**
 * Queue system for the management of asynchronous operations.
 *
 * Anybody can add an item to the queue and any application can
 * register itself to process an item from the queue.
 *
 * An item in the queue is considered as fully processed when all the
 * handlers have processed it successfully.
 *
 * To push a new item in the queue:
 *
 * <code>
 * $item = new IDF_Queue();
 * $item->type = 'new_commit';
 * $item->payload = array('what', 'ever', array('data'));
 * $item->create();
 * </code>
 *
 * To process one item from the queue, you first need to register an
 * handler, by adding the following in your relations.php file before
 * the return statement or in your config file.
 *
 * <code>
 * Pluf_Signal::connect('IDF_Queue::processItem', 
 *                       array('YourApp_Class', 'processItem'));
 * </code>
 *
 * The processItem method will be called with two arguments, the first
 * is the name of the signal ('IDF_Queue::processItem') and the second
 * is an array with:
 *
 * <code>
 * array('item' => $item,
 *       'res' => array('OtherApp_Class::handler' => false,
 *                      'FooApp_Class::processItem' => true));
 * </code>
 *
 * When you process an item, you need first to check if the type is
 * corresponding to what you want to work with, then you need to check
 * in 'res' if you have not already processed successfully the item,
 * that is the key 'YourApp_Class::processItem' must be set to true,
 * and then you can process the item. At the end of your processing,
 * you need to modify by reference the 'res' key to add your status.
 *
 * All the data except for the type is in the payload, this makes the
 * queue flexible to manage many different kind of tasks.
 *
 */
class IDF_Queue extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_queue';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'status' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'choices' => array(
                                                     'pending' => 0,
                                                     'in_progress' => 1,
                                                     'need_retry' => 2,
                                                     'done' => 3,
                                                     'error' => 4,
                                                     ),
                                  'default' => 0,
                                  ),
                            'trials' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'default' => 0,
                                  ),
                            'type' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 50,
                                  ),
                            'payload' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => false,
                                  ),
                            'results' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => false,
                                  ),
                            'lasttry_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  ),
                            );
    }

    function preSave($create=false)
    {
        if ($create) {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
            $this->lasttry_dtime = gmdate('Y-m-d H:i:s');
            $this->results = array();
            $this->trials = 0;
            $this->status = 0;
        }
    }

    /**
     * The current item is going to be processed.
     */
    function processItem()
    {
        /**
         * [signal]
         *
         * IDF_Queue::processItem
         *
         * [sender]
         *
         * IDF_Queue
         *
         * [description]
         *
         * This signal allows an application to run an asynchronous
         * job. The handler gets the queue item and the results from
         * the previous run. If the handler key is not set, then the
         * job was not run. If set it can be either true (already done)
         * or false (error at last run).
         *
         * [parameters]
         *
         * array('item' => $item, 'res' => $res)
         *
         */
        $params = array('item' => $this, 'res' => $this->results);
        Pluf_Signal::send('IDF_Queue::processItem',
                          'IDF_Queue', $params);
        $this->status = 3; // Success
        foreach ($params['res'] as $handler=>$ok) {
            if (!$ok) {
                $this->status = 2; // Set to need retry
                $this->trials += 1;
                break;
            }
        }
        $this->results = $params['res'];
        $this->lasttry_dtime = gmdate('Y-m-d H:i:s');
        $this->update();
    }

    /** 
     * Parse the queue.
     *
     * It is a signal handler to just hook itself at the right time in
     * the cron job performing the maintainance work.
     *
     * The processing relies on the fact that no other processing jobs
     * must run at the same time. That is, your cron job must use a
     * lock file or something like to not run in parallel.
     *
     * The processing is simple, first get 500 queue items, mark them
     * as being processed and for each of them call the processItem()
     * method which will trigger another event for processing.
     *
     * If you are processing more than 500 items per batch, you need
     * to switch to a different solution.
     *
     */
    public static function process($sender, &$params)
    {
        $where = 'status=0 OR status=2';
        $items = Pluf::factory('IDF_Queue')->getList(array('filter'=>$where,
                                                           'nb'=> 500));
        Pluf_Log::event(array('IDF_Queue::process', $items->count()));
        foreach ($items as $item) {
            $item->status = 1;
            $item->update();
        }
        foreach ($items as $item) {
            $item->status = 1;
            $item->processItem();
        }
    }
}
