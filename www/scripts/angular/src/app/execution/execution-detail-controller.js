import _ from 'lodash';

import './execution-link-issue.tpl.html';

export default ExecutionDetailCtrl;

ExecutionDetailCtrl.$inject = [
    '$scope',
    '$state',
    '$sce',
    '$rootScope',
    'gettextCatalog',
    'ExecutionService',
    'DefinitionService',
    'SharedPropertiesService',
    'ArtifactLinksGraphService',
    'ArtifactLinksGraphModalLoading',
    'NewTuleapArtifactModalService',
    'ExecutionRestService',
    'LinkedArtifactsService',
    'TlpModalService',
];

function ExecutionDetailCtrl(
    $scope,
    $state,
    $sce,
    $rootScope,
    gettextCatalog,
    ExecutionService,
    DefinitionService,
    SharedPropertiesService,
    ArtifactLinksGraphService,
    ArtifactLinksGraphModalLoading,
    NewTuleapArtifactModalService,
    ExecutionRestService,
    LinkedArtifactsService,
    TlpModalService,
) {
    var execution_id,
        campaign_id,
        issue_config = SharedPropertiesService.getIssueTrackerConfig();

    $scope.pass                        = pass;
    $scope.fail                        = fail;
    $scope.block                       = block;
    $scope.notrun                      = notrun;
    $scope.sanitizeHtml                = sanitizeHtml;
    $scope.getStatusLabel              = getStatusLabel;
    $scope.linkMenuIsVisible           = issue_config.permissions.create && issue_config.permissions.link;
    $scope.canCreateIssue              = issue_config.permissions.create;
    $scope.canLinkIssue                = issue_config.permissions.link;
    $scope.showLinkToNewBugModal       = showLinkToNewBugModal;
    $scope.showLinkToExistingBugModal  = showLinkToExistingBugModal;
    $scope.showArtifactLinksGraphModal = showArtifactLinksGraphModal;
    $scope.showEditArtifactModal       = showEditArtifactModal;
    $scope.closeLinkedIssueAlert       = closeLinkedIssueAlert;
    $scope.refreshLinkedIssues         = refreshLinkedIssues;
    $scope.linkedIssueId               = null;
    $scope.linkedIssueAlertVisible     = false;

    initialization();
    resetTimer();

    $scope.$on('controller-reload', function() {
        initialization();
    });

    $scope.$on('$destroy', function() {
        var future_execution_id = parseInt($state.params.execid, 10);
        if (! _.isFinite(future_execution_id)) {
            $rootScope.$broadcast('execution-detail-destroy');
            ExecutionRestService.leaveTestExecution(execution_id);
            ExecutionService.removeViewTestExecution(execution_id, SharedPropertiesService.getCurrentUser());
        }
    });

    function initialization() {
        execution_id = parseInt($state.params.execid, 10);
        campaign_id  = parseInt($state.params.id, 10);

        ExecutionService.loadExecutions(campaign_id);

        if (isCurrentExecutionLoaded()) {
            retrieveCurrentExecution();
        } else {
            waitForExecutionToBeLoaded();
        }

        $scope.artifact_links_graph_modal_loading = ArtifactLinksGraphModalLoading.loading;
        $scope.edit_artifact_modal_loading        = NewTuleapArtifactModalService.loading;
    }

    function resetTimer() {
        $scope.timer = {
            execution_time: 0
        };
    }

    function showLinkToNewBugModal() {
        function callback(artifact_id) {
            ExecutionRestService
                .linkIssueWithoutComment(artifact_id, $scope.execution)
                .then(function () {
                    $scope.linkedIssueId           = artifact_id;
                    $scope.linkedIssueAlertVisible = true;
                    $scope.refreshLinkedIssues();
                });
        }

        var current_definition = $scope.execution.definition;
        var issue_details = gettextCatalog.getString('Campaign') + ' <em>' + $scope.campaign.label + '</em><br/>'
            + gettextCatalog.getString('Test summary') + ' <em>' + current_definition.summary + '</em><br/>'
            + gettextCatalog.getString('Test description') + '<br/>'
            + '<blockquote>' + current_definition.description + '</blockquote>';

        if ($scope.execution.previous_result.result) {
            issue_details = '<p>' + $scope.execution.previous_result.result + '</p>' + issue_details;
        }

        var prefill_values = [{
            name  : 'details',
            value : issue_details,
            format: 'html'
        }];

        NewTuleapArtifactModalService.showCreation(
            SharedPropertiesService.getIssueTrackerId(),
            null,
            callback,
            prefill_values);
    }

    function showLinkToExistingBugModal() {
        function callback(artifact_id) {
            $scope.linkedIssueId           = artifact_id;
            $scope.linkedIssueAlertVisible = true;
            $scope.refreshLinkedIssues();
        }

        return TlpModalService.open({
            templateUrl : 'execution-link-issue.tpl.html',
            controller  : 'ExecutionLinkIssueCtrl',
            controllerAs: 'modal',
            resolve: {
                modal_model: {
                    test_execution: $scope.execution
                },
                modal_callback: callback
            }
        });
    }

    function closeLinkedIssueAlert() {
        $scope.linkedIssueAlertVisible = false;
    }

    function showArtifactLinksGraphModal(execution) {
        ArtifactLinksGraphService.showGraphModal(execution);
    }

    function showEditArtifactModal($event, definition) {
        var when_left_mouse_click = 1;

        var old_category    = $scope.execution.definition.category;
        var current_user_id = SharedPropertiesService.getCurrentUser().id;

        function callback(artifact_id) {
            var executions = ExecutionService.getExecutionsByDefinitionId(artifact_id);

            return DefinitionService.getDefinitionById(artifact_id).then(function(definition) {
                _(executions).forEach(function(execution) {
                    $scope.execution = ExecutionService.executions[execution.id];

                    $scope.execution.definition.category = definition.category;
                    $scope.execution.definition.description = definition.description;
                    $scope.execution.definition.summary = definition.summary;

                    updateExecution(definition, old_category);
                });

                retrieveCurrentExecution();
            });
        }

        if ($event.which === when_left_mouse_click) {
            $event.preventDefault();

            DefinitionService.getArtifactById(definition.id).then(function(artifact) {
                NewTuleapArtifactModalService.showEdition(
                    current_user_id,
                    artifact.tracker.id,
                    artifact.id,
                    callback
                );
            });
        }
    }

    function waitForExecutionToBeLoaded() {
        var unbind = $rootScope.$on('bunchOfExecutionsLoaded', function () {
            if (isCurrentExecutionLoaded()) {
                retrieveCurrentExecution();
            }
        });
        $scope.$on('$destroy', unbind);
    }

    function retrieveCurrentExecution() {
        $scope.execution         = ExecutionService.executions[execution_id];
        $scope.execution.results = '';
        $scope.execution.saving  = false;
    }

    function refreshLinkedIssues() {
        $scope.execution.linked_bugs = [];
        LinkedArtifactsService.getAllLinkedIssues($scope.execution, 0, (bunch_of_linked_issues) => {
            $scope.execution.linked_bugs.push(...bunch_of_linked_issues);
        }).catch(() => {
            ExecutionService.displayErrorMessage(
                $scope.execution,
                gettextCatalog.getString('Error while refreshing the list of linked bugs')
            );
        });
    }

    function isCurrentExecutionLoaded() {
        return typeof ExecutionService.executions[execution_id] !== 'undefined';
    }

    function sanitizeHtml(html) {
        if (html) {
            return $sce.trustAsHtml(html);
        }

        return null;
    }

    function pass(execution) {
        updateTime(execution);
        setNewStatus(execution, "passed");
    }

    function fail(execution) {
        updateTime(execution);
        setNewStatus(execution, "failed");
    }

    function block(execution) {
        updateTime(execution);
        setNewStatus(execution, "blocked");
    }

    function notrun(execution) {
        setNewStatus(execution, "notrun");
    }

    function updateTime(execution) {
        if (execution.time) {
            execution.time += $scope.timer.execution_time;
        }
    }

    function setNewStatus(execution, new_status) {
        execution.saving   = true;
        var execution_time = null;

        if (execution.time) {
            execution_time = execution.time;
        }
        ExecutionRestService.putTestExecution(execution.id, new_status, execution_time, execution.results).then(function(data) {
            ExecutionService.updateTestExecution(data);
            resetTimer();
        }).catch(function(response) {
            ExecutionService.displayError(execution, response);
        });
    }

    function getStatusLabel(status) {
        var labels = {
            passed: 'Passed',
            failed: 'Failed',
            blocked: 'Blocked',
            notrun: 'Not Run'
        };

        return labels[status];
    }

    function updateExecution(definition, old_category) {
        var category_updated = definition.category;

        if (category_updated === null) {
            category_updated = ExecutionService.UNCATEGORIZED;
        }

        if (old_category === null) {
            old_category = ExecutionService.UNCATEGORIZED;
        }

        var category_exist           = categoryExists(ExecutionService.categories, category_updated);
        var execution_already_placed = executionAlreadyPlaced($scope.execution, ExecutionService.categories, category_updated);

        if (! execution_already_placed) {
            removeCategory(ExecutionService.categories[old_category].executions, $scope.execution);
        }

        if (category_exist && ! execution_already_placed) {
            ExecutionService.categories[category_updated].executions.push($scope.execution);
        } else if (! category_exist && ! execution_already_placed) {
            ExecutionService.categories[category_updated] = {
                label: category_updated,
                executions: [$scope.execution]
            };
        }
    }

    function categoryExists(categories, category_updated) {
        return _.has(categories, category_updated);
    }

    function executionAlreadyPlaced(scopeExecution, categories, category_updated) {
        return _.has(categories, function(category) {
            return _.has(category.executions, scopeExecution.id, category_updated);
        });
    }

    function removeCategory(executions, scopeExecution) {
        _.remove(executions, function(execution) {
            return execution.id === scopeExecution.id;
        });
    }
}
