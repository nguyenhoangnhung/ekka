<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

use App\Repositories\CustomerRepository;
use App\Models\Customer;
use App\Models\CustomerAuth;
use App\Models\CustomerDetail;
use Carbon\Carbon;

use Redirect,Response,Config;
use Mail;
use App\Mail\MailNotify;

use Session;
use Hash;
use DB;

class AuthController extends Controller
{
    protected $customer;
    protected $customer_auth;
    protected $customer_detail;

    public function __construct(Customer $customer, CustomerAuth $customer_auth, CustomerDetail $customer_detail){
        $this->customer         = new CustomerRepository($customer);
        $this->customer_auth    = new CustomerRepository($customer_auth);
        $this->customer_detail  = new CustomerRepository($customer_detail);
    }
    public function register(Request $request){ 
        if ($this->customer_auth->check_email($request->email)) {
            return redirect()->back()->with('error', 'Email đã tồn tại!!');  
        }else{
            $secret_key = mt_rand(1, 9999999);
            $data_auth = [
                "customer_id"   => 1,
                "secret_key"    => $secret_key,
                "email"         => $request->email,
                "password"      => Hash::make($request->password),
            ];
            $auth_register = $this->customer_auth->create($data_auth);

            $data_detail = [
                "customer_id"   => $auth_register->id,
                "name"          => $request->name,
                "phone"         => $request->phone,
            ];
            $this->customer_detail->create($data_detail);

            // Đăng kí thành công, tự động đăng nhập, bỏ token Guest, tạo token Auth
            Cookie::queue(Cookie::forget('_token_'));
            // open  ~ mỗi IP chỉ register 1 tài khoản
            // $tokenAuth = $user_id . '$' . Hash::make($user_id . '$' . $secret_key);
            $tokenAuth = $auth_register->id . '$' . Hash::make($auth_register->id . '$' . $secret_key);
            Cookie::queue('_token_', $tokenAuth, 2628000);
            return redirect()->back(); 
        }
    }
    public function forgot(Request $request){ 
        $check_email = $this->customer_auth->check_email($request->email);
        if ($check_email) {
            $user_info = $this->customer_auth->get_profile_email($request->email);
            $verify_code = mt_rand(1, 9999999);
            $data = [
                'verify_code' => $verify_code
            ];
            $this->customer_auth->update($data, $user_info->id);
            $code = Hash::make($verify_code);
            $email = $request->email;
            $url = route('customer.reset', ['code' => $code, 'email' => $email]);
            Mail::send('email-forgot', array('url'=> $url), function($message) use ($email) {
                $message->from('techchat2110@gmail.com', 'Ekka store');
                $message->to($email)->subject('Ekka store khôi phục mật khẩu!');
            });
            return redirect()->back()->with('success', 'Đường dẫn đổi mật khẩu đã được gửi về Email.'); 
        }else{
            return redirect()->back()->with('error', 'Email không tồn tại'); 
        }
    }
    public function reset(Request $request){ 
        $check_email = $this->customer_auth->check_email($request->email);
        if ($check_email) {
            $user_info = $this->customer_auth->get_profile_email($request->email);
            $verify_code = $user_info->verify_code;  
            if (Hash::check($verify_code, $request->code)) {
                $secret_key = mt_rand(1, 9999999);
                $data = [
                    'secret_key' => $secret_key,
                    'password' => Hash::make($request->password),
                ];
                $this->customer_auth->update($data, $user_info->id);
 
                Cookie::queue(Cookie::forget('_token_')); 
                $tokenAuth = $user_info->id . '$' . Hash::make($user_info->id . '$' . $secret_key);
                Cookie::queue('_token_', $tokenAuth, 2628000);
                return redirect()->back(); 
            }else{
                return redirect()->route("customer.view.forgot")->with('error', 'Có lỗi sảy ra'); 
            } 
        }else{
            return redirect()->back()->with('error', 'Email không tồn tại'); 
        }
    }
    public function login(Request $request){
        $customer_id = $this->customer_auth->check_login($request);
        if ($customer_id) {
            Cookie::queue(Cookie::forget('_token_'));
            Cookie::queue('_token_', $this->customer_auth->createTokenClient($customer_id), 2628000);
            return redirect()->route("customer.view.profile")->with('success', 'Đăng nhập thành công'); 
        }else{
            return redirect()->back()->with('error_login', 'Tên tài khoản hoặc mật khẩu không chính xác'); 
        }
    }
    public function logout(Request $request){
        Cookie::queue(Cookie::forget('_token_'));
        return redirect()->route('customer.view.login')->with('success_logout', 'Đăng xuất thành công');  
    } 
}
