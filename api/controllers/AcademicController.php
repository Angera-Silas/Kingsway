/** POST /api/academic/year-transition/validate-readiness - Validate year readiness
     *
     * Gate: the readiness pass is also the cutover — when every check passes it
     * closes the outgoing year and activates the new one. It therefore carries
     * the same academic_year_manage / system_admin guard as close-year and
     * summary; without it any authenticated user could switch the system to a
     * new academic year.
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionValidateReadiness($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /** POST /api/academic/year-transition/regress-to-editable
     *  Regress workflow to make a previous stage editable
     *
     * Allows going back to a previous stage's data for editing while keeping
     * the current_stage unchanged. The data from the specified previous stage
     * is loaded and made available for editing, and the regression is recorded
     * in the workflow stage history.
     *
     * Gate: academic_year_manage / system_admin
     *
     * @param int $instance_id Workflow instance ID
     * @array $data Contains 'to_stage' code of the previous stage to make editable
     * @return array ['success' => bool, 'message' => string, 'data' => array, 'current_stage' => string, 'from_stage' => string, 'to_stage' => string]
     */
    public function postYearTransitionRegressToEditable($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        if (empty($instanceId)) {
            return $this->badRequest('Academic year transition instance_id is required');
        }
        $toStage = $data['to_stage'] ?? null;
        if (empty($toStage)) {
            return $this->badRequest('Target stage (to_stage) is required');
        }
        return $this->handleResponse($this->api->regressToStageEditable($instanceId, $data));
    }

    /**