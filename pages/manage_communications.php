<?php
/**
 * Manage Communications Hub - Landing Page
 * Modern dashboard showing overview of all communication channels.
 * Embedded via home.php?route=manage_communications.
 */
?>

<div class="row g-3">
    <div class="col-12">
        <div class="app-page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-page-icon bg-primary-subtle text-primary-emphasis"><i class="bi bi-chat-square-text"></i></div>
                <div>
                    <h4 class="app-page-title mb-0">Communications Hub</h4>
                    <span class="app-page-subtitle">Manage messages, announcements, and notifications across all channels</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="createInternalRequestBtn">
                    <i class="bi bi-plus-lg me-1"></i>New Request
                </button>
                <button class="btn btn-success btn-sm" id="composeMessageBtn">
                    <i class="bi bi-envelope me-1"></i>Compose
                </button>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-primary bg-primary-subtle"><i class="bi bi-chat-left-text"></i></div>
            <div>
                <div class="app-stat-value" id="totalCommunications">0</div>
                <div class="app-stat-label">Messages</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-success bg-success-subtle"><i class="bi bi-bell"></i></div>
            <div>
                <div class="app-stat-value" id="totalAnnouncements">0</div>
                <div class="app-stat-label">Announcements</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-warning bg-warning-subtle"><i class="bi bi-audio-track"></i></div>
            <div>
                <div class="app-stat-value" id="unreadConversations">0</div>
                <div class="app-stat-label">Unread</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-info bg-info-subtle"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="app-stat-value" id="scheduledCount">0</div>
                <div class="app-stat-label">Scheduled</div>
            </div>
        </div>
    </div>

    <!-- Channel cards -->
    <div class="col-12">
        <div class="app-panel">
            <h6 class="mb-3"><i class="bi bi-grid me-2 text-primary"></i>Communication Channels</h6>
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=communications/messages_inbox" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="messages">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-chat-text me-2"></i>Messages</h6>
                                    <span class="badge bg-light text-dark border" id="messagesCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Internal staff-to-staff forum</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to messages</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_announcements" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="announcements">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-megaphone me-2"></i>Announcements</h6>
                                    <span class="badge bg-success-subtle text-success-emphasis" id="announcementsCount">0</span>
                                </div>
                                <p class="card-text text-muted small">School-wide public notices</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to announcements</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_sms" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="sms">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-chat-dots me-2"></i> SMS</i></h6>
                                    <span class="badge bg-info text-white border" id="smsCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Parent and staff SMS messages</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to SMS</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_email" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="email">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-envelope me-2"></i>Email</h6>
                                    <span class="badge bg-secondary text-white border" id="emailCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Official school email communications</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to email</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_whatsapp" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="whatsapp">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-brushes me-2"></i>WhatsApp</h6>
                                    <span class="badge bg-success text-white border" id="whatsappCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Parent portal WhatsApp messages</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to WhatsApp</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-12">
        <div class="app-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Activity</h6>
                <button class="btn btn-sm btn-outline-primary" id="refreshActivities">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div id="recentActivities">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th class="d-none d-md-table-cell">Created</th>
                            </tr>
                        </thead>
                        <tbody id="activitiesTablebody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Conversation view modal -->
<div class="modal fade" id="conversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-text me-2 text-primary"></i>Message Thread</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent" class="mb-3"></div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="newMessageInput" placeholder="Type a message...">
                    <button class="btn btn-success btn-sm" id="sendThreadReply"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_communications.js?v=<?= time(); ?>"></script>