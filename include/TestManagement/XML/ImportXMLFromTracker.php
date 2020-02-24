<?php
/**
 * Copyright (c) Enalean, 2019 - Present. All Rights Reserved.
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

declare(strict_types=1);

namespace Tuleap\TestManagement\XML;

use Project;
use SimpleXMLElement;
use Tuleap\Project\UGroupRetrieverWithLegacy;
use Tuleap\TestManagement\Step\Definition\Field\StepDefinition;
use Tuleap\Tracker\XML\TrackerXmlImportFeedbackCollector;
use XML_RNGValidator;

class ImportXMLFromTracker
{
    /**
     * @var XML_RNGValidator
     */
    private $rng_validator;

    /**
     * @var UGroupRetrieverWithLegacy
     */
    private $ugroup_retriever_with_legacy;

    public function __construct(
        XML_RNGValidator $rng_validator,
        UGroupRetrieverWithLegacy $ugroup_retriever_with_legacy
    ) {
        $this->rng_validator                = $rng_validator;
        $this->ugroup_retriever_with_legacy = $ugroup_retriever_with_legacy;
    }

    public function validateXMLImport(SimpleXMLElement $xml): void
    {
        $this->rng_validator->validate(
            $xml,
            realpath(TESTMANAGEMENT_RESOURCE_DIR . '/testmanagement_external_fields.rng')
        );
    }

    public function getInstanceFromXML(SimpleXMLElement $testmanagement, $project, $feedback_collector): StepDefinition
    {
        $att = $testmanagement->attributes();
        assert($att !== null);
        $row            = [
            'name'              => (string)$testmanagement->name,
            'label'             => (string)$testmanagement->label,
            'rank'              => (int)$att['rank'],
            'use_it'            => isset($att['use_it']) ? (int)$att['use_it'] : 1,
            'scope'             => isset($att['scope']) ? (string)$att['scope'] : 'P',
            'required'          => isset($att['required']) ? (int)$att['required'] : 0,
            'notifications'     => isset($att['notifications']) ? (int)$att['notifications'] : 0,
            'description'       => (string)$testmanagement->description,
            'id'                => 0,
            'tracker_id'        => 0,
            'parent_id'         => 0,
            'original_field_id' => null,
        ];
        $original_field = null;

        $step_def = new StepDefinition(
            $row['id'],
            $row['tracker_id'],
            $row['parent_id'],
            $row['name'],
            $row['label'],
            $row['description'],
            $row['use_it'],
            $row['scope'],
            $row['required'],
            $row['notifications'],
            $row['rank'],
            $original_field
        );

        $this->setPermissions($testmanagement, $step_def, $project, $feedback_collector);

        return $step_def;
    }

    public function setPermissions(
        SimpleXMLElement $xml,
        StepDefinition $step_def,
        Project $project,
        TrackerXmlImportFeedbackCollector $feedback_collector
    ): void {
        foreach ($xml->permissions->permission as $permission) {
            $ugroup_name = (string)$permission['ugroup'];
            $ugroup_id   = $this->ugroup_retriever_with_legacy->getUGroupId($project, $ugroup_name);
            if (!$ugroup_id) {
                $feedback_collector->addWarnings(
                    "Custom ugroup '$ugroup_name' does not seem to exist for " . $project->getUnixName() . " project."
                );
                continue;
            }
            $type = (string)$permission['type'];
            $step_def->setCachePermission($ugroup_id, $type);
        }
    }
}
