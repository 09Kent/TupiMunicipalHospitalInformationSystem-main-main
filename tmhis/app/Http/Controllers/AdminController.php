<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

require_once app_path('Services/Admin/HospitalInfo.php');
require_once app_path('Services/Admin/Department.php');
require_once app_path('Services/Admin/ServiceFee.php');
require_once app_path('Services/Admin/UserManager.php');
require_once app_path('Services/Admin/RolePermission.php');
require_once app_path('Services/Admin/AuditLog.php');
require_once app_path('Services/Admin/BackupManager.php');

class AdminController extends Controller
{
    public function dashboard()
    {
        $hospitalModel = new \HospitalInfo();
        $deptModel = new \Department();
        $feeModel = new \ServiceFee();
        $userModel = new \UserManager();
        $roleModel = new \RolePermission();
        $auditModel = new \AuditLog();
        $backupModel = new \BackupManager();

        $hospitalInfo = $hospitalModel->get();
        $departments = $deptModel->getAll();
        $serviceFees = $feeModel->getAll();
        $users = $userModel->getAll();
        $roles = $roleModel->getRoles();
        $recentLogs = $auditModel->getActivityLogs(20);
        $backups = $backupModel->getBackupHistory();
        $currentUser = \Session::getCurrentUser();

        return view('admin.dashboard', compact(
            'hospitalInfo',
            'departments',
            'serviceFees',
            'users',
            'roles',
            'recentLogs',
            'backups',
            'currentUser'
        ));
    }

    public function usersApi(Request $request)
    {
        $model = new \UserManager();
        $action = $request->input('action', 'list');

        try {
            if ($action === 'list') {
                return response()->json(['success' => true, 'data' => $model->getAll()]);
            } elseif ($action === 'create' && $request->isMethod('post')) {
                $id = $model->create($request->all());
                \AuditLog::log('Create User Account', 'User Account Administration', "Created user: {$request->input('Username')} (Role: {$request->input('Role')})", (string)$id);
                return response()->json(['success' => true, 'id' => $id, 'message' => 'User account created successfully with encrypted password.']);
            } elseif ($action === 'update' && $request->isMethod('post')) {
                $id = (int)$request->input('UserID', 0);
                $success = $model->update($id, $request->all());
                \AuditLog::log('Update User Account', 'User Account Administration', "Updated user ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'User account updated successfully.' : 'Failed to update user.']);
            } elseif ($action === 'toggle_status' && $request->isMethod('post')) {
                $id = (int)$request->input('id', 0);
                $status = $request->input('status', 'Active');
                $success = $model->toggleStatus($id, $status);
                \AuditLog::log('Toggle User Status', 'User Account Administration', "Set user ID $id status to $status", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? "User status changed to $status." : 'Failed to update status.']);
            } elseif ($action === 'reset_password' && $request->isMethod('post')) {
                $id = (int)$request->input('id', 0);
                $newPassword = $request->input('new_password', '');
                if (strlen($newPassword) < 6) {
                    return response()->json(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
                }
                $success = $model->resetPassword($id, $newPassword);
                \AuditLog::log('Reset Password', 'User Account Administration', "Reset password for user ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'Password reset successfully with secure bcrypt hash.' : 'Failed to reset password.']);
            } elseif ($action === 'delete' && $request->isMethod('post')) {
                $id = (int)$request->input('id', 0);
                $success = $model->delete($id);
                \AuditLog::log('Delete User Account', 'User Account Administration', "Deleted user ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'User deleted successfully.' : 'Cannot delete system administrator.']);
            }
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function configApi(Request $request)
    {
        $model = new \HospitalInfo();
        $action = $request->input('action', 'get');
        if ($action === 'list') $action = 'get';

        try {
            if ($action === 'get') {
                return response()->json(['success' => true, 'data' => $model->get()]);
            } elseif ($action === 'update' && $request->isMethod('post')) {
                $success = $model->update($request->all());
                \AuditLog::log('Update Hospital Info', 'Hospital Info & Core Config', 'Updated hospital metadata', '1');
                return response()->json(['success' => $success, 'message' => $success ? 'Hospital configuration updated successfully.' : 'Failed to update configuration.']);
            }
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function departmentsApi(Request $request)
    {
        $model = new \Department();
        $action = $request->input('action', 'list');

        try {
            if ($action === 'list') {
                return response()->json(['success' => true, 'data' => $model->getAll()]);
            } elseif ($action === 'create' && $request->isMethod('post')) {
                $id = $model->create($request->all());
                \AuditLog::log('Create Department', 'Department Management', "Created department: {$request->input('DepartmentName')}", (string)$id);
                return response()->json(['success' => true, 'id' => $id, 'message' => 'Department created successfully.']);
            } elseif ($action === 'update' && $request->isMethod('post')) {
                $id = (int)$request->input('DepartmentID', 0);
                $success = $model->update($id, $request->all());
                \AuditLog::log('Update Department', 'Department Management', "Updated department ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'Department updated successfully.' : 'Failed to update department.']);
            } elseif ($action === 'delete' && $request->isMethod('post')) {
                $id = (int)$request->input('id', 0);
                $success = $model->delete($id);
                \AuditLog::log('Delete Department', 'Department Management', "Deleted department ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'Department deleted successfully.' : 'Failed to delete department.']);
            }
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function serviceFeesApi(Request $request)
    {
        $model = new \ServiceFee();
        $action = $request->input('action', 'list');

        try {
            if ($action === 'list') {
                return response()->json(['success' => true, 'data' => $model->getAll()]);
            } elseif ($action === 'create' && $request->isMethod('post')) {
                $id = $model->create($request->all());
                \AuditLog::log('Create Service Fee', 'Service Fee Schedule', "Created service fee: {$request->input('ServiceName')}", (string)$id);
                return response()->json(['success' => true, 'id' => $id, 'message' => 'Service fee schedule created successfully.']);
            } elseif ($action === 'update' && $request->isMethod('post')) {
                $id = (int)$request->input('FeeID', 0);
                $success = $model->update($id, $request->all());
                \AuditLog::log('Update Service Fee', 'Service Fee Schedule', "Updated service fee ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'Service fee updated successfully.' : 'Failed to update fee.']);
            } elseif ($action === 'delete' && $request->isMethod('post')) {
                $id = (int)$request->input('id', 0);
                $success = $model->delete($id);
                \AuditLog::log('Delete Service Fee', 'Service Fee Schedule', "Deleted service fee ID: $id", (string)$id);
                return response()->json(['success' => $success, 'message' => $success ? 'Service fee deleted successfully.' : 'Failed to delete fee.']);
            }
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function backupsApi(Request $request)
    {
        $model = new \BackupManager();
        $action = $request->input('action', 'list');

        try {
            if ($action === 'list') {
                return response()->json(['success' => true, 'data' => $model->getBackupHistory()]);
            } elseif ($action === 'create' && $request->isMethod('post')) {
                $type = $request->input('type', 'Full Database');
                $result = $model->createBackup($type);
                \AuditLog::log('Create Database Backup', 'Data Backup & Restore', "Generated backup: {$result['filename']}", (string)$result['id']);
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup archive generated successfully.',
                    'filename' => $result['filename'],
                    'size' => $result['size_formatted']
                ]);
            }
            return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function rolesApi(Request $request)
    {
        $model = new \RolePermission();
        return response()->json(['success' => true, 'data' => $model->getRoles()]);
    }

    public function logsApi(Request $request)
    {
        $model = new \AuditLog();
        return response()->json(['success' => true, 'data' => $model->getActivityLogs(50)]);
    }
}
