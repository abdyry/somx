<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\Admin\StaffDataTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\EmailController;
use App\Http\Helpers\Common;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Services\Mail\UserStatusChangeMailService;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    protected $helper;
    protected $email;
    protected $currency;
    protected $user;

    public function __construct()
    {
        $this->helper = new Common();
        $this->email = new EmailController();
        $this->currency = new Currency();
        $this->user = new User();
    }

    public function index(StaffDataTable $dataTable)
    {
        $data['menu'] = 'users';
        $data['sub_menu'] = 'staff_list';

        return $dataTable->render('admin.staff.index', $data);
    }

    public function create()
    {
        $data['menu'] = 'users';
        $data['sub_menu'] = 'staff_list';
        $data['roles'] = Role::select('id', 'display_name')->where('user_type', 'Staff')->get();

        return view('admin.users.createStaff', $data);
    }

    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required',
        ];

        $fieldNames = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'password' => 'Password',
            'role' => 'Role',
        ];

        $validator = \Validator::make($request->all(), $rules);
        $validator->setAttributeNames($fieldNames);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = new User();
        $user->first_name = ucfirst($request->first_name);
        $user->last_name = ucfirst($request->last_name);
        $user->email = $request->email;
        $user->password = \Hash::make($request->password);
        $user->status = 'Active';
        $user->role_id = $request->role;
        $user->type = 'Staff';
        $user->save();

        $this->helper->one_time_message('success', 'Staff Added Successfully');

        return redirect('admin/staff');
    }

    public function Edit($id)
    {
        $data['menu'] = 'users';
        $data['sub_menu'] = 'staff_list';

        $data['users'] = User::find($id);
        $data['roles'] = Role::select('id', 'display_name')->where('user_type', 'Staff')->get();

        return view('admin.users.editStaff', $data);
    }

    public function update(Request $request)
    {
        if ($request->isMethod('post')) {
            $rules = [
                'first_name' => 'required|max:30|regex:/^[a-zA-Z\s]*$/',
                'last_name' => 'required|max:30|regex:/^[a-zA-Z\s]*$/',
                'email' => 'required|email|unique:users,email,'.$request->id,
                'password' => 'nullable|min:6|confirmed',
                'password_confirmation' => 'nullable|min:6',
                'status' => 'required',
            ];

            $validator = \Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            } else {
                try {
                    \DB::beginTransaction();
                    $user = User::find($request->id);
                    $user->first_name = $request->first_name;
                    $user->last_name = $request->last_name;
                    $user->email = $request->email;
                    $user->role_id = $request->role;
                    $user->status = $request->status;

                    $formattedPhone = ltrim($request->phone, '0');
                    if (!empty($request->phone)) {
                        $user->phone = preg_replace("/[\s-]+/", '', $formattedPhone);
                        $user->defaultCountry = $request->user_defaultCountry;
                        $user->carrierCode = $request->user_carrierCode;
                        $user->formattedPhone = $request->formattedPhone;
                    } else {
                        $user->phone = null;
                        $user->defaultCountry = null;
                        $user->carrierCode = null;
                        $user->formattedPhone = null;
                    }

                    if (!is_null($request->password) && !is_null($request->password_confirmation)) {
                        $user->password = \Hash::make($request->password);
                    }
                    $user->save();

                    RoleUser::where(['user_id' => $request->id, 'user_type' => 'User'])->update(['role_id' => $request->role]);

                    \DB::commit();

                    if ($request->status != $user->status) {
                        (new UserStatusChangeMailService())->send($user);
                    }

                    $this->helper->one_time_message('success', __('The :x has been successfully saved.', ['x' => __('staff')]));

                    return redirect(config('adminPrefix').'/staff');
                } catch (\Exception $e) {
                    \DB::rollBack();
                    $this->helper->one_time_message('error', $e->getMessage());

                    return redirect(config('adminPrefix').'/staff');
                }
            }
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if ($user) {
            try {
                \DB::beginTransaction();
                // Deleting Non-Relational Table Entries

                ActivityLog::where(['user_id' => $id])->delete();
                RoleUser::where(['user_id' => $id, 'user_type' => 'User'])->delete();

                $user->delete();

                \DB::commit();

                $this->helper->one_time_message('success', __('The :x has been successfully deleted.', ['x' => __('staff')]));

                return redirect(config('adminPrefix').'/staff');
            } catch (\Exception $e) {
                \DB::rollBack();
                $this->helper->one_time_message('error', $e->getMessage());

                return redirect(config('adminPrefix').'/staff');
            }
        }
    }
}
