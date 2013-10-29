<?php
/**
 * Copyright (c) Enalean, 2013. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

class Tracker_REST_WorkflowRepresentation {
    /** @var int */
    public $field_id;

    /** @var bool*/
    public $is_used;

    /** @var Tracker_REST_WorkflowRulesRepresentation */
    public $rules;

    /** @var array {@type Tracker_REST_WorkflowTransitionRepresentation} */
    public $transitions = array();

    public function __construct($id, $is_used, Tracker_REST_WorkflowRulesRepresentation $rules, array $transitions) {
        $this->field_id = $id;
        $this->is_used  = $is_used;
        $this->rules    = $rules;
        $this->transitions = $transitions;
    }
}