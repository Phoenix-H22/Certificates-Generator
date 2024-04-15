<?php

namespace App\Http\Controllers;

use App\Models\subscriped_users;
use App\Models\Setting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function cert()
    {
        $name = Auth::user()->name;
        return view('certificate',compact('name'));
    }
    public function news(Request $request)
    {
         // validate the email
         $request->validate([
              'email' => 'required|email|unique:subscriped_users'
            ]);
         // create a new record in the database
         subscriped_users::create(
                [
                    'email' => $request->email
                ]
         );
    //    return success message to the blade
       return back()->with('success','You have been subscribed to our newsletter');
    }
}
