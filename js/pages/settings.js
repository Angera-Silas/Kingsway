/**
 * Settings Page Controller
 * Manages system configuration, user accounts, and roles
 */

let usersTable = null;
let rolesTable = null;
let permissionsTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || '') + '/index.php';
        return;
    }

    initializeSettingsTables();
    loadSettingsStatistics();
    attachSettingsEventListeners();
});

function initializeSettingsTables() {
    // Users Table
    usersTable = new DataTable('usersTable', {
        apiEndpoint: '/users/index',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'full_name', label: 'Name', sortable: true },
            { field: 'email', label: 'Email' },
            { field: 'phone', label: 'Phone' },
            { field: 'role_name', label: 'Role' },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 'active': 'success', 'inactive': 'danger', 'suspended': 'warning' }
            },
            { field: 'last_login', label: 'Last Login', type: 'date' }
        ],
        searchFields: ['full_name', 'email'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'users_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'users_edit' },
            { id: 'resetPassword', label: 'Reset Password', icon: 'bi-key', variant: 'warning', permission: 'users_edit' },
            { id: 'deactivate', label: 'Deactivate', icon: 'bi-x-circle', variant: 'danger', permission: 'users_delete' }
        ]
    });

    // Roles Table
    rolesTable = new DataTable('rolesTable', {
        apiEndpoint: '/settings/roles',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'role_name', label: 'Role', sortable: true },
            { field: 'description', label: 'Description' },
            { field: 'user_count', label: 'Users Assigned', type: 'number' },
            { field: 'permission_count', label: 'Permissions', type: 'number' },
            { 
                field: 'status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: { 'active': 'success', 'inactive': 'secondary' }
            }
        ],
        searchFields: ['role_name', 'description'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'settings_view' },
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'settings_edit' },
            { id: 'permissions', label: 'Manage Permissions', icon: 'bi-shield-lock', variant: 'info', permission: 'settings_edit' }
        ]
    });

    // Permissions Table
    permissionsTable = new DataTable('permissionsTable', {
        apiEndpoint: '/settings/permissions',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'permission_key', label: 'Permission', sortable: true },
            { field: 'permission_label', label: 'Label' },
            { field: 'module', label: 'Module' },
            { field: 'role_count', label: 'Assigned Roles', type: 'number' }
        ],
        searchFields: ['permission_key', 'permission_label'],
        rowActions: [
            { id: 'edit', label: 'Edit', icon: 'bi-pencil', variant: 'warning', permission: 'settings_edit' }
        ]
    });
}

async function loadSettingsStatistics() {
    try {
        const [usersRes, rolesRes, permsRes] = await Promise.all([
            window.API.users.index().catch(() => null),
            window.API.system.getRoles().catch(() => null),
            window.API.system.getPermissions().catch(() => null),
        ]);
        const unwrap = (res) =>
            Array.isArray(res?.data)
                ? res.data
                : Array.isArray(res?.data?.users)
                  ? res.data.users
                  : Array.isArray(res?.data?.roles)
                    ? res.data.roles
                    : Array.isArray(res?.data?.permissions)
                      ? res.data.permissions
                      : Array.isArray(res) ? res : [];
        const users = unwrap(usersRes);
        const roles = unwrap(rolesRes);
        const permissions = unwrap(permsRes);
        document.getElementById('totalUsers').textContent = users.length;
        document.getElementById('activeUsers').textContent = users.filter(
            (u) => String(u.status || '').toLowerCase() === 'active'
        ).length;
        document.getElementById('totalRoles').textContent = roles.length;
        document.getElementById('totalPermissions').textContent = permissions.length;
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function attachSettingsEventListeners() {
    document.getElementById('createUserBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createUserModal'));
        modal.show();
    });

    document.getElementById('createRoleBtn')?.addEventListener('click', () => {
        const modal = new bootstrap.Modal(document.getElementById('createRoleModal'));
        modal.show();
    });

    document.getElementById('backupBtn')?.addEventListener('click', async () => {
        if (await window.confirmAction('Confirm', 'Create database backup? This may take a few minutes.')) {
            await performBackup();
        }
    });

    document.getElementById('usersSearchInput')?.addEventListener('keyup', (e) => {
        usersTable.search(e.target.value);
    });

    document.getElementById('rolesSearchInput')?.addEventListener('keyup', (e) => {
        rolesTable.search(e.target.value);
    });

    document.getElementById('permissionsSearchInput')?.addEventListener('keyup', (e) => {
        permissionsTable.search(e.target.value);
    });
}

async function performBackup() {
    try {
        await window.API.system.createBackup();
        await window.infoDialog('Notice', 'Backup created successfully');
    } catch (error) {
        console.error('Backup failed:', error);
        await window.infoDialog('Notice', 'Backup failed. Check console for details.');
    }
}

window.SettingsRealtimeController = {
    async refreshData() {
        await Promise.all([
            loadSettingsStatistics(),
            usersTable?.refresh?.(),
            rolesTable?.refresh?.(),
            permissionsTable?.refresh?.(),
        ]);
    },
};
window.APIRealtime?.register?.('SettingsRealtimeController', window.SettingsRealtimeController, ['system']);
