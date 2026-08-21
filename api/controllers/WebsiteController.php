<?php
namespace App\API\Controllers;

use App\API\Modules\website\WebsiteManager;

/**
 * WebsiteController — CRUD for all public website content tables.
 * Endpoint: /api/website/{resource}
 *
 * All DB/business logic lives in WebsiteManager; this controller only
 * validates auth/RBAC, reads input, delegates, and responds.
 */
class WebsiteController extends BaseController
{
    private $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = new WebsiteManager();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERMISSION HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getEffectivePerms(): array
    {
        $user = $this->user;
        if (!$user) return [];
        return (array) ($user['effective_permissions'] ?? []);
    }

    private function hasPerm(string $perm): bool
    {
        if ($perm === 'website_view' && !$this->user && $this->isReadRequest()) {
            return true;
        }
        if (!$this->user) return false;
        $perms = $this->getEffectivePerms();
        return in_array($perm, $perms) || in_array(str_replace('_', '.', $perm), $perms);
    }

    private function isReadRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    private function requirePerm(string $perm)
    {
        if (!$this->hasPerm($perm) && !$this->hasPerm('website_settings_manage')) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
        return null;
    }

    public function forbidden($message = 'Access forbidden')
    {
        http_response_code(403);
        return json_encode(['status' => 'error', 'message' => $message, 'data' => null, 'code' => 403]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESPONSE HANDLER
    // ─────────────────────────────────────────────────────────────────────────

    private function handleResponse($result)
    {
        if (is_array($result)) {
            $code = (int) ($result['code'] ?? 200);
            if (isset($result['success'])) {
                return $result['success']
                    ? ($code === 201
                        ? $this->created($result['data'] ?? [], $result['message'] ?? 'Created')
                        : $this->success($result['data'] ?? [], $result['message'] ?? 'OK'))
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? ($code === 201
                        ? $this->created($result['data'] ?? [], $result['message'] ?? 'Created')
                        : $this->success($result['data'] ?? [], $result['message'] ?? 'OK'))
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    private function mapError($code, $message, $data = [])
    {
        if ($code >= 500) {
            return $this->serverError($message, $data);
        }
        if ($code === 404) {
            return $this->notFound($message);
        }
        if ($code === 401) {
            return $this->unauthorized($message);
        }
        if ($code === 403) {
            return $this->forbidden($message);
        }
        return $this->badRequest($message, $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATS  GET /api/website/stats
    // ─────────────────────────────────────────────────────────────────────────

    public function getStats($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        $result = $this->manager->getStats();
        // Anonymous visitors may read the showcase counts (news/events/jobs/
        // gallery/downloads + the live students/staff headcounts shown on the
        // homepage) but not the admin workflow KPIs (received applications,
        // new inquiries, job applications) — those stay staff-only.
        if (!$this->user && is_array($result) && isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_intersect_key(
                $result['data'],
                array_flip(['news', 'events', 'jobs', 'gallery', 'downloads', 'students', 'staff'])
            );
        }
        return $this->handleResponse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OPEN TERMS  GET /api/website/terms
    // ─────────────────────────────────────────────────────────────────────────

    public function getTerms($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getOpenTerms());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTIVE GRADES  GET /api/website/grades
    // ─────────────────────────────────────────────────────────────────────────

    public function getGrades($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getActiveGrades());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS ARTICLES  /api/website/news
    // ─────────────────────────────────────────────────────────────────────────

    public function getNews($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getNews($id, $data));
    }

    public function postNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['content'])) return $this->badRequest('Title and content are required.');
        $author = trim(($this->user['first_name'] ?? '') . ' ' . ($this->user['last_name'] ?? ''));
        return $this->handleResponse($this->manager->createNews($data, $author ?: 'Admin'));
    }

    public function putNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        return $this->handleResponse($this->manager->updateNews($id, $data));
    }

    public function deleteNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        return $this->handleResponse($this->manager->deleteNews($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVENTS  /api/website/events
    // ─────────────────────────────────────────────────────────────────────────

    public function getEvents($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getEvents($id, $data));
    }

    public function postEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['event_date'])) return $this->badRequest('Title and event date are required.');
        return $this->handleResponse($this->manager->createEvent($data));
    }

    public function putEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        return $this->handleResponse($this->manager->updateEvent($id, $data));
    }

    public function deleteEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        return $this->handleResponse($this->manager->deleteEvent($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GALLERY  /api/website/gallery
    // ─────────────────────────────────────────────────────────────────────────

    public function getGallery($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getGallery($data));
    }

    public function postGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (empty($data['image_url'])) return $this->badRequest('Image URL is required.');
        return $this->handleResponse($this->manager->createGalleryItem($data));
    }

    public function putGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        return $this->handleResponse($this->manager->updateGalleryItem($id, $data));
    }

    public function deleteGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        return $this->handleResponse($this->manager->deleteGalleryItem($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOADS  /api/website/downloads
    // ─────────────────────────────────────────────────────────────────────────

    public function getDownloads($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getDownloads($data));
    }

    public function postDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (empty($data['title'])) {
            return $this->badRequest('Title is required.');
        }
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->badRequest('Choose a school document to upload.');
        }

        return $this->handleResponse($this->manager->createDownload($data, $_FILES['file'], $this->userId()));
    }

    public function putDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (!$id) {
            return $this->badRequest('Download ID required.');
        }

        $file = $_FILES['file'] ?? [];
        return $this->handleResponse($this->manager->updateDownload($id, $data, $file, $this->userId()));
    }

    public function deleteDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (!$id) {
            return $this->badRequest('Download ID required.');
        }

        return $this->handleResponse($this->manager->deleteDownload($id, $this->userId()));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB VACANCIES  /api/website/jobs
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobs($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getJobs($id, $data));
    }

    public function postJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['deadline'])) return $this->badRequest('Title and deadline are required.');
        return $this->handleResponse($this->manager->createJob($data));
    }

    public function putJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        return $this->handleResponse($this->manager->updateJob($id, $data));
    }

    public function deleteJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        return $this->handleResponse($this->manager->closeJob($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS  /api/website/settings
    // ─────────────────────────────────────────────────────────────────────────

    public function getSettings($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getSettings());
    }

    public function putSettings($id = null, $data = [], $segments = [])
    {
        $isAdmissionNumberSetting = in_array((string) ($data['key'] ?? ''), [
            'admission_no_format', 'admission_no_start_sequence',
            'staff_no_format', 'staff_no_start_sequence'
        ], true);
        if (!$isAdmissionNumberSetting) {
            $guard = $this->requirePerm('website_settings_manage');
            if ($guard) return $guard;
        }
        if (empty($data['key'])) return $this->badRequest('Setting key is required.');
        return $this->handleResponse($this->manager->saveSetting($data['key'], (string) ($data['value'] ?? '')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTENT BLOCKS  /api/website/content
    // ─────────────────────────────────────────────────────────────────────────

    public function getContent($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getContent());
    }

    public function putContent($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['key'])) return $this->badRequest('Content key is required.');
        return $this->handleResponse($this->manager->saveContent($data['key'], (string) ($data['value'] ?? '')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // APPLICATIONS (read-only)  /api/website/applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getApplications($data));
    }

    public function putApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Application ID required.');
        if (empty($data['status'])) return $this->badRequest('Status is required.');
        return $this->handleResponse($this->manager->updateApplicationStatus($id, (string) $data['status']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB APPLICATIONS /api/website/job-applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getJobApplications());
    }

    public function putJobApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id || empty($data['status'])) return $this->badRequest('Application ID and status are required.');
        return $this->handleResponse($this->manager->updateJobApplicationStatus((int)$id, (string)$data['status'], $this->userId(), $data['notes'] ?? null));
    }

    public function postJobApplicationsInterview($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id) return $this->badRequest('Application ID required.');
        return $this->handleResponse($this->manager->scheduleJobInterview((int)$id, $data, $this->userId()));
    }

    public function putJobApplicationsInterview($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id) return $this->badRequest('Interview ID required.');
        return $this->handleResponse($this->manager->completeJobInterview((int)$id, $data, $this->userId()));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTACT INQUIRIES (read-only)  /api/website/inquiries
    // ─────────────────────────────────────────────────────────────────────────

    public function getInquiries($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getInquiries());
    }

    public function putInquiries($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Inquiry ID required.');
        if (!empty($data['reply'])) {
            return $this->handleResponse($this->manager->replyInquiry((int) $id, (string) $data['reply'], (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0)));
        }
        if (empty($data['status'])) return $this->badRequest('Status is required.');
        return $this->handleResponse($this->manager->updateInquiryStatus($id, (string) $data['status']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS CATEGORIES  /api/website/categories
    // ─────────────────────────────────────────────────────────────────────────

    public function getCategories($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getCategories());
    }

    public function postCategories($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['name'])) return $this->badRequest('Category name required.');
        return $this->handleResponse($this->manager->createCategory((string) $data['name'], (string) ($data['color'] ?? '#198754')));
    }

    public function deleteCategories($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Category ID required.');
        return $this->handleResponse($this->manager->deactivateCategory($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GENERIC TABLE CRUD  /api/website/{leadership|programs|facilities|history|values|departments|benefits}
    // ─────────────────────────────────────────────────────────────────────────

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats($id, $data, $segments);
    }

    public function getLeadership($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipHierarchy());
    }

    public function getLeadershipLevels($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipLevels());
    }

    public function getLeadershipPositions($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipPositions($data['level_id'] ?? null));
    }
    public function getPrograms($id = null, $data = [], $segments = [])   { return $this->genericRead('programs', $id, $data); }
    public function getFacilities($id = null, $data = [], $segments = []) { return $this->genericRead('facilities', $id, $data); }
    public function getHistory($id = null, $data = [], $segments = [])    { return $this->genericRead('history', $id, $data); }
    public function getValues($id = null, $data = [], $segments = [])     { return $this->genericRead('values', $id, $data); }
    public function getBenefits($id = null, $data = [], $segments = [])   { return $this->genericRead('benefits', $id, $data); }
    public function getTestimonials($id = null, $data = [], $segments = []) { return $this->genericRead('testimonials', $id, $data); }

    public function getDepartments($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getDepartments($id));
    }

    public function postLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->createLeadershipEntry($data));
    }
    public function postPrograms($id = null, $data = [], $segments = [])   { return $this->genericWrite('createRecord', 'programs', $data); }
    public function postFacilities($id = null, $data = [], $segments = []){ return $this->genericWrite('createRecord', 'facilities', $data); }
    public function postHistory($id = null, $data = [], $segments = [])    { return $this->genericWrite('createRecord', 'history', $data); }
    public function postValues($id = null, $data = [], $segments = [])     { return $this->genericWrite('createRecord', 'values', $data); }
    public function postBenefits($id = null, $data = [], $segments = [])   { return $this->genericWrite('createRecord', 'benefits', $data); }
    public function postTestimonials($id = null, $data = [], $segments = []) { return $this->genericWrite('createRecord', 'testimonials', $data); }

    public function postDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->createDepartment($data));
    }

    public function putLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->updateLeadershipEntry($id, $data));
    }
    public function putPrograms($id = null, $data = [], $segments = [])   { return $this->genericWrite('updateRecord', 'programs', $data, $id); }
    public function putFacilities($id = null, $data = [], $segments = []) { return $this->genericWrite('updateRecord', 'facilities', $data, $id); }
    public function putHistory($id = null, $data = [], $segments = [])    { return $this->genericWrite('updateRecord', 'history', $data, $id); }
    public function putValues($id = null, $data = [], $segments = [])     { return $this->genericWrite('updateRecord', 'values', $data, $id); }
    public function putBenefits($id = null, $data = [], $segments = [])   { return $this->genericWrite('updateRecord', 'benefits', $data, $id); }
    public function putTestimonials($id = null, $data = [], $segments = []) { return $this->genericWrite('updateRecord', 'testimonials', $data, $id); }

    public function putDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->updateDepartment($id, $data));
    }

    public function deleteLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteLeadershipEntry($id));
    }
    public function deletePrograms($id = null, $data = [], $segments = [])   { return $this->genericDelete('programs', $id); }
    public function deleteFacilities($id = null, $data = [], $segments = []) { return $this->genericDelete('facilities', $id); }
    public function deleteHistory($id = null, $data = [], $segments = [])    { return $this->genericDelete('history', $id); }
    public function deleteValues($id = null, $data = [], $segments = [])     { return $this->genericDelete('values', $id); }
    public function deleteBenefits($id = null, $data = [], $segments = [])   { return $this->genericDelete('benefits', $id); }
    public function deleteTestimonials($id = null, $data = [], $segments = []) { return $this->genericDelete('testimonials', $id); }

    public function deleteDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteDepartment($id));
    }

    private function genericRead(string $resource, $id, array $data)
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getRecord($resource, $id));
    }

    private function genericWrite(string $method, string $resource, array $data, $id = null)
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->{$method}($resource, $data, $id));
    }

    private function genericDelete(string $resource, $id)
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteRecord($resource, $id));
    }

    private function userId()
    {
        return $this->user['user_id'] ?? $this->user['id'] ?? null;
    }
}
