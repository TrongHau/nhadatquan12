<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 8/21/2018
 * Time: 3:33 PM
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Library\Helpers;
use Illuminate\Support\Facades\Auth;
use  App\Models\DistrictModel;
use  App\Models\ProvinceModel;
use  App\Models\StreetModel;
use  App\Models\WardModel;
use Illuminate\Support\Facades\Hash;
use  App\User;
use  App\Models\VerifySMSModel;
use App\Models\MailTokenModel;
use App\Models\PhoneModel;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('article.manage_for_lease');
    }
    public function logout()
    {
        Auth::logout();
        return redirect()->back();
    }
    public function changeProfile(Request $request) {
        $province = ProvinceModel::get();
        return view('user.change_profile', compact('province'));
    }
    public function storeProfile(Request $request) {
        $this->validate($request, [
            'name' => 'required|max:50',
//            'phone' => 'required|unique:users',
            'province_id' => 'required',
            'district_id' => 'required',
            'nick_name' => 'max:200',
            'tax_code' => 'max:200',
            'skype' => 'max:200',
            'zalo' => 'max:200',
            'viber' => 'max:200',
            'company' => 'max:200',
            'facebook' => 'max:200',
        ]);
        $profile = [
            'name' => $request->name,
            'gender' => $request->gender,
            'nick_name' => $request->nick_name,
            'birth_day' => date("Y/m/d", strtotime($request->input('birth_day'))),
            'province_id' => $request->province_id,
            'province' => $request->province_id ? ProvinceModel::find($request->province_id)->_name : '',
            'district_id' => $request->district_id,
            'district' => ($request->district_id && $request->province_id) ? DistrictModel::where('id', $request->district_id)->where('_province_id', $request->province_id)->first()->_name : '',
            'ward_id' => $request->ward_id,
            'ward' => ($request->ward_id && $request->district_id && $request->province_id) ? WardModel::where('id', $request->ward_id)->where('_province_id', $request->province_id)->where('_district_id', $request->district_id)->first()->_name : '',
            'street_id' => $request->street_id,
            'street' => ($request->street_id && $request->district_id && $request->province_id) ? StreetModel::where('id', $request->street_id)->where('_province_id', $request->province_id)->where('_district_id', $request->district_id)->first()->_name : '',
            'address' => $request->address,
//            'phone' => $request->phone,
            'tax_code' => $request->tax_code,
            'skype' => $request->skype,
            'zalo' => $request->zalo,
            'viber' => $request->viber,
            'company' => $request->company,
            'facebook' => $request->facebook,

        ];
        $result = User::where('id', Auth::user()->id)->update($profile);
        return redirect()->route('user.changeProfile')->with('success', 'Cập nhật thành công');

    }
    public function storeAvatar(Request $request) {
        if($request->input('avatar')){
            $path = Helpers::file_path(Auth::user()->id, STORAGE_AVATAR_PATH, true);
            $fileNameAvatar = Helpers::saveBase64Image($request->input('avatar'), $path, Auth::user()->id, 'png');
            $user = Auth::user();
            $user->avatar = $fileNameAvatar;
            $user->save();
            return Helpers::ajaxResult(true, 'Cập nhật ảnh đại diện thành công.', null);
        }
    }
    public function changePassword(Request $request) {
        return view('user.change_password');
    }
    public function storePassword(Request $request) {
        $this->validate($request, [
            'current_password' => 'required|max:255|min:6',
            'password' => 'required|max:255|min:6',
            'repassword' => 'required|max:255|min:6|same:password',
        ]);
        if($request->input('password') && $request->input('current_password')) {
            if(Hash::check($request->input('current_password'), Auth::User()->password))
            {
                $user = Auth::user();
                $user->password = Hash::make($request->input('password'));
                $user->save();
                return redirect()->route('user.changePassword')->with('success', 'Cập nhật mật khẩu thành công.');
            }
        }
        return redirect()->route('user.changePassword')->with('error', 'Xác nhận mật khẩu không chính xác');
    }
    public function verifyEmail(Request $request, $token)
    {
        $tokenVerify = MailTokenModel::where('token', $token)->first();
        if(!$tokenVerify)
            return view('errors.text_error')->with('message', 'Mã xác nhận email của bạn không tồn tại.');
        $user = User::where('email', $tokenVerify->email)->where('status', DEACTIVE_USER)->first();
        if(!$user)
            return view('errors.text_error')->with('message', 'Lỗi xác thực email, kiểm tra tình trạng tài khoản của bạn.');
        User::find($user->id)->update([
            'status' => ACTIVE_USER
        ]);
        MailTokenModel::deleteToken($tokenVerify->email);
        Auth::login($user);
        return redirect()->route('user.changeProfile')->with('success', 'Kích hoạt tài khoản thành công.');
    }
    public function getVerifyMobile(Request $request)
    {
        session_start();
        if(!$request->phone)
            return Helpers::ajaxResult(false, 'Vui lòng điền số điện thoại.', null);
        if(User::where('phone', $request->phone)->first())
            return Helpers::ajaxResult(false, 'Số điện thoại đã sử dụng', null);
        $existOtp = VerifySMSModel::where(['user_id' => Auth::user()->id ? Auth::user()->id : session()->getId(), 'type' => 'verify_phone'])->first();
        if($existOtp) {
            $timeExpried = $existOtp->otp_time_expried - time();
            if($timeExpried > 0) {
                return Helpers::ajaxResult(true, 'Thời gian nhập mã xác thực còn lại:', ['phone' => $existOtp->phone, 'expried' => date('i', $timeExpried) . ' phút']);
            }else{
                $existOtp->delete();
            }
        }
        $phoneFlag = PhoneModel::find($request->phone);
        if($phoneFlag) {
            if($phoneFlag->status == 0)
                return Helpers::ajaxResult(false, 'Số điện thoại của bạn đã bị khóa, vui lòng liên hệ '.env('PHONE_CONTACT').' để được hỗ trợ.', null);
            if($phoneFlag->count_sms > 5) {
                if(date("Y-m-d", strtotime($phoneFlag->updated_at)) <=  date("Y-m-d")) {
                    return Helpers::ajaxResult(false, 'Số điện thoại của bạn đã gửi quá 5 lần trong ngày, vui lòng liên hệ '.env('PHONE_CONTACT').' để được hỗ trợ.', null);
                }else{
                    $phoneFlag->count_sms = 0;
                }
            }
            $phoneFlag->count_sms = $phoneFlag->count_sms + 1;
            $phoneFlag->save();
        }else{
            PhoneModel::create([
                'phone' => $request->phone,
                'user_id' => Auth::user()->id ? Auth::user()->id : '',
                'count_sms' => 1,
            ]);
        }
        $otp = mt_rand(1000, 9999);
        $Content = "Ma xac thuc nhadatquan12.com.vn cua ban la: " . $otp;
        Helpers::sendSMS($request->phone, $Content);
        $newOtp = VerifySMSModel::create([
            'user_id' => Auth::user()->id ? Auth::user()->id : session()->getId(),
            'phone' => $request->phone,
            'otp' => $otp,
            'otp_time_expried' =>strtotime(TIME_EXPIRED_OTP),
            'type' => 'verify_phone',

        ]);
        return Helpers::ajaxResult(true, 'Thời gian nhập mã xác thực còn lại:', ['phone' => $newOtp->phone, 'expried' => '05 phút']);
    }
    public function setVerifyMobile(Request $request)
    {
        if(!$request->phone)
            return Helpers::ajaxResult(false, 'Vui lòng điền số điện thoại.', null);
        if(!$request->otp)
            return Helpers::ajaxResult(false, 'Vui lòng điền mã xác thực.', null);

        $existOtp = VerifySMSModel::where(['user_id' => Auth::user()->id ? Auth::user()->id : session()->getId(), 'type' => 'verify_phone', 'phone' => $request->phone])->first();
        if($existOtp) {
            $phoneFlag = PhoneModel::find($existOtp->phone);
            if($phoneFlag && $phoneFlag->status == 0)
                return Helpers::ajaxResult(false, 'Số điện thoại của bạn đã bị khóa, vui lòng liên hệ '.env('PHONE_CONTACT').' để được hỗ trợ.', null);
            $timeExpried = $existOtp->otp_time_expried - time();
            if($timeExpried > 0) {
                $user = User::where('id', $existOtp->user_id)->update([
                    'phone' => $existOtp->phone
                ]);
                $existOtp->delete();
                session_start();
                $_SESSION['verify_phone'] = $existOtp->phone;
                return Helpers::ajaxResult(true, 'Xác thực số điện thoại '.$existOtp->phone.' thành công!', ['phone' => $existOtp->phone]);
            }else{
                return Helpers::ajaxResult(false, 'Mã xác thực đã hết hạn, vui lòng gửi lại mã xác thực', null);
            }
        }
        return Helpers::ajaxResult(false, 'Mã xác thực không chính xác.', null);
    }
}
