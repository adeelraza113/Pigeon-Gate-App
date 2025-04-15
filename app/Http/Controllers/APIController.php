<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use App\Services\AblyService;
use Ably\AblyRest;
use Throwable;



//models
use App\Models\User;
use App\Models\Pigeons;
use App\Models\Shops;
use App\Models\Products;
use App\Models\Articles;
use App\Models\Events;
use App\Models\Followers;
use App\Models\Clubs;
use App\Models\ClubMembers;
use App\Models\ClubPost;
use App\Models\Boost;
use App\Models\Report;
use App\Models\Review;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Notification;
use App\Models\ClubScore;
use App\Mail\SendOtpMail;



class APIController extends Controller
{
   public function login(Request $request)
{
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid login details'
        ], 401);
    }

    try {
        $user = User::where('email', $request['email'])->firstOrFail();

        // ✅ Check if email is verified
        if (!$user->email_verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email not verified. Please verify your email before logging in.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $profileImageUrl = Storage::disk('public')->url('uploads/' . $user->profile_image);

        return response()->json([
            'status' => 'success',
            'user_id' => $user->id,
            'profile_image' => $profileImageUrl,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);

    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}





  public function register(Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'unique:users',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "User already exists!",
                ]);
            }
            $fileName = "";
            if (isset($request->profile_image) && $request->profile_image) {
                $file = base64_decode($request->profile_image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'country' => $request->country,
                'phone' => $request->phone,
                'address' => $request->address,
                'profile_image' => $fileName,
                'password' => bcrypt($request->password),
            ]);
    
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully!',
                'user_id' => $user->id,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
public function generateOtp(Request $request)
{
    try {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if ($validator->fails()) {
            Log::warning('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Store OTP in database (or cache)
        DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['otp' => $otp, 'expires_at' => $expiresAt]
        );

        // Send the OTP email
        try {
            Mail::to($request->email)->send(new SendOtpMail($otp));
            Log::info('OTP generated and email sent', ['email' => $request->email, 'otp' => $otp]);
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to send OTP email', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['status' => 'success', 'message' => 'OTP generated successfully', 'otp' => $otp], 200);
    } catch (\Exception $e) {
        Log::error('Failed to generate OTP', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Failed to generate OTP', 'message' => $e->getMessage()], 500);
    }
}


    public function resetPasswordWithOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric',
                'password' => 'required|confirmed|min:6',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            // Verify OTP
            $record = DB::table('password_resets')->where('email', $request->email)->first();
            if (!$record || $record->otp != $request->otp || Carbon::now()->isAfter($record->expires_at)) {
                return response()->json(['error' => 'Invalid or expired OTP'], 400);
            }
            // Update password
            $user = User::where('email', $request->email)->first();
            $user->update(['password' => Hash::make($request->password)]);
            // Delete OTP record
            DB::table('password_resets')->where('email', $request->email)->delete();
            return response()->json(['status' => 'success','message' => 'Password reset successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset password', 'message' => $e->getMessage()], 500);
        }
    }

    // pigeon apis
 public function getAllPigeons(Request $request) {
    try {
        $user = $request->user();
        $search = $request->query('search');

        // Automatically delete pigeons older than 30 days
        $thirtyDaysAgo = now()->subDays(30);
        Pigeons::where('created_at', '<', $thirtyDaysAgo)->delete();

        $query = Pigeons::join('users', 'pigeons.user_id', '=', 'users.id')
            ->select(
                'pigeons.*', 
                'users.profile_image as user_image', 
                'users.created_at as member_since', 
                'users.name as user_name', 
                'users.id as user_id', 
                'users.phone as user_phone'
            );

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $terms = explode('&', $search); // Split search terms by '&'
                foreach ($terms as $term) {
                    $trimmedTerm = trim($term); // Trim spaces around the term
                    $q->orWhere('pigeons.name', 'LIKE', '%' . $trimmedTerm . '%')
                      ->orWhere('users.name', 'LIKE', '%' . $trimmedTerm . '%');
                }
            });
        }

        $pigeons = $query->orderBy('pigeons.created_at', 'desc')->get();

        foreach ($pigeons as $pigeon) {
            // Decode and format pigeon images
            if (!empty($pigeon->images)) {
                $pigeon->images = json_decode($pigeon->images, true);
                $images = array_map(function ($image) {
                    return Storage::disk('public')->url('uploads/' . $image);
                }, $pigeon->images);
                $pigeon->images = $images;
            }

            // Format user image URL
            if (!empty($pigeon->user_image)) {
                $pigeon->user_image = Storage::disk('public')->url('uploads/' . $pigeon->user_image);
            }

            // Check if the current user follows the pigeon user
            $follower = Followers::where('following', $pigeon->user_id)
                ->where('followed_by', $user->id)
                ->first();
            $pigeon->user_follow_text = !empty($follower) ? "Following" : "Follow";

            // Check if the current user liked the pigeon
            $liked = DB::table('pigeon_user_actions')
                ->where('user_id', $user->id)
                ->where('pigeon_id', $pigeon->id)
                ->where('liked', 1)
                ->exists();
            $pigeon->isLiked = $liked;
        }

        return response()->json([
            'status' => 'success',
            'data'   => $pigeons,
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}



    
    public function getPigeonByID(Request $request, $id){

        try{
            if(empty($id)){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please provide valid id!',
                ], 500);
            }
            $pigeon = Pigeons::where('id', $id)->get()->first();
            if(!empty($pigeon->images)){
                $pigeon->images = json_decode($pigeon->images, true);
            }
            return response()->json([
                'status' => 'success',
                'data'   => $pigeon,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addPigeon(Request $request){

        try{
            $user = $request->user();
            $fileNames = array();
            if($request->images){
                $counter = 1;
                foreach($request->images as $image){
                    $file = base64_decode($image);
                    $fileName = time() .'_'. $counter . '.png';
                    $fileNames[] = $fileName;
                    Storage::disk('public')->put('uploads/' . $fileName, $file);
                    $counter += 1;
                }
            }
            $pigeon = Pigeons::create([
                'name'          => $request->name,
                'price'         => $request->price,
                'gender'        => $request->gender, 
                'color'         => $request->color, 
                'ring_number'   => $request->ring_number,
                'weight'        => $request->weight,
                'vaccination'   => $request->vaccination, 
                'location'      => $request->location, 
                'description'   => $request->description,
                'images'        => json_encode($fileNames),
                'user_id'       => $user->id
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Pigeon created successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // shop apis
    public function createShop(Request $request){
        try{
            $fileName = "";
            $user = $request->user();
            if($request->image){
                $file = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $shop = Shops::create([
                'shop_name' => $request->shop_name,
                'owner_name' => isset($request->owner_name) && $request->owner_name ? $request->owner_name : '',
                'website' => $request->website,
                'category' => $request->category,
                'opening_hours' => $request->opening_hours,
                'return_policy' => $request->return_policy,
                'shipping_policy' => $request->shipping_policy,
                'image' => $fileName,
                'user_id' => $user->id
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Shop created successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllShops(Request $request)
{
    try {
        $search = $request->query('search');

        $query = Shops::join('users', 'shops.user_id', '=', 'users.id')
            ->select(
                'shops.id',
                'user_id',
                'shop_name',
                'category',
                'users.name as owner_name',
                'users.created_at as member_since',
                'users.address',
                'users.phone',
                'image'
            )
            ->orderBy('shops.created_at', 'desc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('shops.shop_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('shops.category', 'LIKE', '%' . $search . '%');
            });
        }

        $shops = $query->get();

        foreach ($shops as $shop) {
            $shop->image = Storage::disk('public')->url('uploads/' . $shop->image);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $shops,
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}



    public function getShopByID(Request $request, $id){

        try{
            if(empty($id)){
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please provide valid id!',
                ], 500);
            }
            $shop = Shops::where('id', $id)->get()->first();
            return response()->json([
                'status' => 'success',
                'data'   => $shop,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function searchShops(Request $request, $keyword){
        try{
            $shop = Shops::where('owner_name', 'LIKE', '%'.$keyword.'%')->get();
            return response()->json([
                'status' => 'success',
                'data'   => $shop,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // product apis
    public function addProduct(Request $request){
        try{
            $fileName = "";
            $user = $request->user();
            if($request->image){
                $file = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $product = Products::create([
                'name' => $request->name,
                'shop_id' => $request->shop_id,
                'category' => $request->category,
                'size' => $request->size,
                'price' => $request->price,
                'delivery' => $request->delivery,
                'description' => $request->description,
                'image' => $fileName
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    

public function getAllProductsByShop(Request $request)
{
    try {
        // Validate shop_id parameter
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
        ]);

        $shop_id = $validated['shop_id'];

        // Fetch products with reviews (only ReasonForReview and name fields) along with user details
        $products = Products::where('shop_id', $shop_id)
            ->with(['reviews' => function ($query) {
                $query->select('product_id', 'ReasonForReview');
            }])
            ->get();

        foreach ($products as $product) {
            // Decode and format product images
            if (!empty($product->image)) {
                $product->image = Storage::disk('public')->url('uploads/' . $product->image);
            }

            // Get user details related to the shop's user_id
            $user = User::find($product->shop->user_id);

            if ($user) {
                $product->user_name = $user->name;
                $product->user_phone = $user->phone;
                $product->user_email = $user->email;
                $product->user_address = $user->address;
            }

            $reasonGroups = [];
            foreach ($product->reviews as $review) {
                // Decode the JSON ReasonForReview field
                $decodedReviews = json_decode($review->ReasonForReview, true);

                if (is_array($decodedReviews)) {
                    foreach ($decodedReviews as $reasonData) {
                        $name = $reasonData['name'];
                        $reasons = $reasonData['reasons'] ?? [];

                        if (isset($reasonGroups[$name])) {
                            $reasonGroups[$name]['reasons'] = array_unique(array_merge(
                                $reasonGroups[$name]['reasons'],
                                $reasons
                            ));
                        } else {
                            $reasonGroups[$name] = [
                                'name' => $name,
                                'reasons' => $reasons,
                            ];
                        }
                    }
                }
            }
            $product->ReasonForReview = array_values($reasonGroups);
            unset($product->reviews);
        }

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while processing the request',
            'error' => $e->getMessage(),
        ], 500);
    }
}



    // articles apis
    public function addArticle(Request $request){
        try{
            $user = $request->user();
            $fileName = "";
            if($request->image){
                $file = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $product = Articles::create([
                'title' => $request->title,
                'author_name' => $request->author_name,
                'publication_date' => $request->publication_date,
                'content' => $request->content,
                'image' => $fileName,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Article created successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
 public function getAllArticles(Request $request)
{
    try {
        $search = $request->query('search');
        
        if (!empty($search)) {
            $articles = Articles::join('users', 'articles.user_id', '=', 'users.id')
                ->select(
                    'articles.*',
                    'users.profile_image as user_image',
                    'users.name as user_name',
                    'articles.likes',
                    'articles.views',
                    'articles.comments_count' // Directly fetch the comments_count from the articles table
                )
                ->where('title', 'LIKE', '%'.$search.'%')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $articles = Articles::join('users', 'articles.user_id', '=', 'users.id')
                ->select(
                    'articles.*',
                    'users.profile_image as user_image',
                    'users.name as user_name',
                    'articles.likes',
                    'articles.views',
                    'articles.comments_count' // Directly fetch the comments_count from the articles table
                )
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Ensure the images are properly formatted
        foreach ($articles as $article) {
            $article->image = Storage::disk('public')->url('uploads/' . $article->image);
            if (!empty($article->user_image)) {
                $article->user_image = Storage::disk('public')->url('uploads/' . $article->user_image);
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => $articles,
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}


    // events apis
    public function addEvent(Request $request){
        try{
            $user = $request->user();
            $fileName = "";
            if($request->image){
                $file = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $product = Events::create([
                'title' => $request->title,
                'date' => $request->date,
                'description' => $request->description,
                'image' => $fileName,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Event created successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function getAllEvents(Request $request){

        try{
            $search = $request->query('search');
            if(!empty($search)){
                $events = Events::join('users', 'events.user_id', '=', 'users.id')
                    ->select('events.*', 'users.profile_image as user_image', 'users.name as user_name')
                    ->where('title', 'LIKE', '%'.$search.'%')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }else{
                $events = Events::join('users', 'events.user_id', '=', 'users.id')
                    ->select('events.*', 'users.profile_image as user_image', 'users.name as user_name')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
            foreach($events as $event){
                $event->image = Storage::disk('public')->url('uploads/'.$event->image);
                if(!empty($event->user_image)){
                    $event->user_image = Storage::disk('public')->url('uploads/'.$event->user_image);
                }
            }
            return response()->json([
                'status' => 'success',
                'data'   => $events,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function followUser(Request $request){
        try{
            $user = $request->user();
            Followers::create([
                'following' => $request->user_id,
                'followed_by' => $user->id
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Followed successfully!',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

   public function likePigeonPost(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'id' => 'required|exists:pigeons,id',
        ]);

        // Get the authenticated user
        $user = auth()->user(); // This requires the auth:sanctum middleware
        $userId = $user->id;

        // Check if the user has already liked the pigeon post
        $action = DB::table('pigeon_user_actions')
            ->where('user_id', $userId)
            ->where('pigeon_id', $request->id)
            ->first();

        if ($action && $action->liked) {
            return response()->json([
                'status' => 'success',
                'message' => 'Like already recorded.',
                'isLiked' => true,
            ]);
        }

        // Add or update the user's action
        if (!$action) {
            DB::table('pigeon_user_actions')->insert([
                'user_id' => $userId,
                'pigeon_id' => $request->id,
                'liked' => 1,
                'viewed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('pigeon_user_actions')
                ->where('user_id', $userId)
                ->where('pigeon_id', $request->id)
                ->update(['liked' => 1, 'updated_at' => now()]);
        }

        // Increment the likes column in the pigeons table
        DB::table('pigeons')->where('id', $request->id)->increment('likes');

        return response()->json([
            'status' => 'success',
            'message' => 'Like count updated successfully.',
            'isLiked' => true,
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in likePigeonPost API: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'An internal server error occurred.',
        ], 500);
    }
}


public function viewPigeonPost(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'id' => 'required|exists:pigeons,id',
        ]);

        // Get the authenticated user
        $user = auth()->user(); // This requires the auth:sanctum middleware
        $userId = $user->id;

        // Check if the user has already viewed the pigeon post
        $action = DB::table('pigeon_user_actions')
            ->where('user_id', $userId)
            ->where('pigeon_id', $request->id)
            ->first();

        if ($action && $action->viewed) {
            return response()->json([
                'status' => 'success',
                'message' => 'View already recorded.',
            ]);
        }

        // Add or update the user's action
        if (!$action) {
            DB::table('pigeon_user_actions')->insert([
                'user_id' => $userId,
                'pigeon_id' => $request->id,
                'liked' => 0,
                'viewed' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('pigeon_user_actions')
                ->where('user_id', $userId)
                ->where('pigeon_id', $request->id)
                ->update(['viewed' => 1, 'updated_at' => now()]);
        }

        // Increment the views column in the pigeons table
        DB::table('pigeons')->where('id', $request->id)->increment('views');

        return response()->json([
            'status' => 'success',
            'message' => 'View count updated successfully.',
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in viewPigeonPost API: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'An internal server error occurred.',
        ], 500);
    }
}

   
  public function userDetails(Request $request)
{
    try {
        $user = $request->user();

        // Fetch user details with additional fields
        $user_detail = User::where('id', $user->id)
            ->select(
                'name as user_name',
                'email',
                'profile_image',
                'phone as user_phone',
                'address as user_address',
                'created_at as member_since'
            )
            ->first();
            
        if (!empty($user_detail->profile_image)) {
            $user_detail->profile_image = Storage::disk('public')->url('uploads/' . $user_detail->profile_image);
        }

        $user_detail->posts = Pigeons::where('user_id', $user->id)
            ->select('name', 'created_at as post_date')
            ->get();

        // Fetch user's shops and products
        $user_detail->shop = Shops::where('user_id', $user->id)
            ->select('id', 'shop_name', 'created_at as post_date')
            ->get();

        // Initialize products array
        $user_detail->products = collect();
        foreach ($user_detail->shop as $shop) {
            $products_tmp = Products::where('shop_id', $shop->id)
                ->select('name', 'created_at as product_date')
                ->get();
            $user_detail->products = $user_detail->products->merge($products_tmp);
        }

        // Fetch followers and following counts
        $user_detail->following = followers::where('following', $user->id)->count(); // Count of users following this user
        $user_detail->followers = followers::where('followed_by', $user->id)->count(); // Count of users this user is following

        // Fetch club_score dynamically from clubscore table
        // Fetch club_score dynamically and ensure it is an integer
       $user_detail->club_score = (int) ClubScore::where('user_id', $user->id)->sum('club_score');

        // Keep other values hardcoded
        $user_detail->club_rank = 15;
        $user_detail->rating = 4;

        return response()->json([
            'status' => 'success',
            'data' => $user_detail,
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}




    // clubs api
 public function createClub(Request $request) {
    try {
        $fileName = "";

        if ($request->club_image) {
            $file = base64_decode($request->club_image);
            $fileName = time() . '.png';
            Storage::disk('public')->put('uploads/' . $fileName, $file);
        }

        $club = Clubs::create([
            "user_id" => $request->user_id, 
            "club_name" => $request->club_name,
            "president_name" => $request->president_name,
            "president_account_number" => $request->president_account_number,
            "bank_name" => $request->bank_name,
            "club_image" => $fileName,
            "country_flag" => $request->country_flag,
           "terms_&_conditions" => $request->all()['terms_&_conditions'] ?? null,
            "joining_fee" => $request->joining_fee,
            "role" => 'president' // Set role as 'president' by default
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Club created successfully!',
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}



    public function getAllClubs(Request $request){

        try{
            $user = $request->user();
            $search = $request->query('search');
            if(!empty($search)){
                $clubs = Clubs::where('club_name', 'LIKE', '%'.$search.'%' )
                    ->orderBy('created_at', 'desc')
                    ->get();
            }else{
                $clubs = Clubs::orderBy('created_at', 'desc')
                    ->get();
            }
            foreach($clubs as $club){
                if(!empty($club->club_image)){
                    $club->club_image = Storage::disk('public')->url('uploads/'.$club->club_image);
                }
            }
            return response()->json([
                'status' => 'success',
                'data'   => $clubs,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

   public function getAllMembersOfClub(Request $request, $id)
{
    try {
        $search = $request->query('search');

        if (!empty($search)) {
            $members = ClubMembers::join('users', 'club_members.user_id', '=', 'users.id')
                ->select(
                    'club_members.id as club_member_id', // Added club_members.id
                    'users.id as user_id',
                    'users.profile_image as user_image',
                    'users.created_at as member_since',
                    'users.name as user_name',
                    'club_members.role'
                )
                ->where('club_members.club_id', $id)
                ->where('club_members.request_approved', true)
                ->where('users.name', 'LIKE', '%' . $search . '%')
                ->orderBy('club_members.created_at', 'desc')
                ->get();
        } else {
            $members = ClubMembers::join('users', 'club_members.user_id', '=', 'users.id')
                ->select(
                    'club_members.id as id', // Added club_members.id
                    'users.id as user_id',
                    'users.profile_image as user_image',
                    'users.created_at as member_since',
                    'users.name as user_name',
                    'club_members.role'
                )
                ->where('club_members.club_id', $id)
                ->where('club_members.request_approved', true)
                ->orderBy('club_members.created_at', 'desc')
                ->get();
        }

        foreach ($members as $member) {
            if (!empty($member->user_image)) {
                $member->user_image = Storage::disk('public')->url('uploads/' . $member->user_image);
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => $members,
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    public function getAllFollowRequestsOfClub(Request $request, $id){

        try{
            $members = ClubMembers::join('users', 'club_members.user_id', '=', 'users.id')
                ->select('users.profile_image as user_image', 'users.created_at as member_since', 'users.name as user_name', 'club_members.role')
                ->where('club_members.club_id', $id)
                ->where('club_members.request_approved', false)
                ->orderBy('club_members.created_at', 'desc')
                ->get();
            foreach($members as $member){
                if(!empty($member->user_image)){
                    $member->user_image = Storage::disk('public')->url('uploads/'.$member->user_image);
                }
            }
            return response()->json([
                'status' => 'success',
                'data'   => $members,
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function requetToJoinClub(Request $request){
        try{
            $user = $request->user();
            $fileName = "";
            if($request->payment_image){
                $file = base64_decode($request->payment_image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $file);
            }
            $club = ClubMembers::create([
                'user_id' => $user->id,
                'club_id' => $request->club_id,
                'payment_image' => $fileName,
                'payment_method' => $request->payment_method,
                'role' => 'member',
                'resquest_approved' => false,
            ]);
            return response()->json([
                'status' => 'success',
                'message'   => 'Request created successfully',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function approveClubJoinRequest(Request $request){
        try{
            $club_id = $request->club_id;
            $user_id = $request->user_id;
            ClubMembers::where('club_id', $club_id)
                ->where('user_id', $user_id)
                ->update(['request_approved' => true]);
            return response()->json([
                'status' => 'success',
                'message'   => 'Join request approved successfully',
            ], 200);
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


public function getClubDetails(Request $request)
{
    try {
        $validated = $request->validate([
            'club_id' => 'required|integer|exists:clubs,id',
        ]);
        $club_id = $validated['club_id'];
        $club = Clubs::where('id', $club_id)->first();
        if (!$club) {
            return response()->json([
                'status' => 'error',
                'message' => 'Club ID does not exist',
            ], 404);
        }
        if (!empty($club->club_image)) {
            $club->club_image = Storage::disk('public')->url('uploads/' . $club->club_image);
        }

        // Fetch the user details for the club's user_id
        $club_user = User::where('id', $club->user_id)->first();
        $club_user_image = null;
        if (!empty($club_user->profile_image)) {
            $club_user_image = Storage::disk('public')->url('uploads/' . $club_user->profile_image);
        }

        Log::info('Club Data: ' . json_encode($club));
        Log::info('Club User Data: ' . json_encode($club_user));
        Log::info('Club User Image: ' . $club_user_image);

        $members = ClubMembers::join('users', 'club_members.user_id', '=', 'users.id')
            ->select(
                'users.name as user_name',
                'users.profile_image as user_image',
                'users.email',
                'club_members.role',
                'users.created_at as member_since'
            )
            ->where('club_members.club_id', $club_id)
            ->where('club_members.request_approved', true)
            ->whereIn('club_members.role', ['event_manager', 'score_counter', 'finance'])
            ->get();
        foreach ($members as $member) {
            if (!empty($member->user_image)) {
                $member->user_image = Storage::disk('public')->url('uploads/' . $member->user_image);
            }
        }

        Log::info('Club Members: ' . json_encode($members));

        $posts = ClubPost::where('club_id', $club_id)
            ->select('id', 'description', 'image', 'pigeon_name', 'champion_year', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($posts as $post) {
            if (!empty($post->image)) {
                $post->image = Storage::disk('public')->url('uploads/' . $post->image);
            }
        }

        Log::info('Club Posts: ' . json_encode($posts));

        return response()->json([
            'status' => 'success',
            'data' => [
                'club' => [
                    'id' => $club->id,
                    'user_id' => $club->user_id,
                    'club_name' => $club->club_name,
                    'president_name' => $club->president_name,
                    'role' => $club->role,
                    'club_image' => $club->club_image,
                    'country_flag' => $club->country_flag,
                    'created_at' => $club->created_at,
                    'updated_at' => $club->updated_at,
                    'terms_&_conditions' => $club->{'terms_&_conditions'}, // Retrieve from DB
                    'joining_fee' => $club->joining_fee,
                    'profile_image' => $club_user_image,
                ],
                'members' => $members,
                'posts' => $posts,
            ]
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation error',
            'errors' => $e->errors(),
        ], 422);
    } catch (Exception $e) {
        // Log the exception message
        Log::error('Error: ' . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}





public function assignClubRole(Request $request)
{
    try {
        $request->validate([
            'club_id' => 'required|integer|exists:clubs,id',
            'finance' => 'nullable|integer|exists:users,id',
            'event_manager' => 'nullable|integer|exists:users,id', 
            'score_counter' => 'nullable|integer|exists:users,id', 
        ]);
        $club_id = $request->input('club_id');
        $rolesToAssign = [
            'finance' => $request->input('finance'),
            'event_manager' => $request->input('event_manager'), 
            'score_counter' => $request->input('score_counter'), 
        ];
        foreach ($rolesToAssign as $role => $user_id) {
            if ($user_id) {
                $clubMember = ClubMembers::where('club_id', $club_id)
                    ->where('user_id', $user_id)
                    ->first();
                if ($clubMember) {$clubMember->update(['role' => $role]); } 
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Roles assigned successfully!',
        ], 200);

    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while assigning the roles.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function createClubPost(Request $request)
    {
        $request->validate([
            'club_id' => 'required|exists:clubs,id',
            'description' => 'required|string',
            'image' => 'required', 
            'pigeon_name' => 'nullable|string',
            'champion_year' => 'nullable|string',
        ]);
        try {
            $fileName = null;
            if ($request->has('image')) {
                $decodedImage = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $decodedImage);
            }
            $clubPost = ClubPost::create([
                'club_id' => $request->club_id,
                'description' => $request->description,
                'image' => $fileName,
                'pigeon_name' => $request->pigeon_name,
                'champion_year' => $request->champion_year,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Club post created successfully!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post: ' . $e->getMessage(),
            ], 500);
        }
    }
  
    public function getClubTermsAndJoiningFee(Request $request)
{
    $club_id = $request->query('id');
    if (!$club_id) {
        return response()->json([
            'status' => 'error',
            'message' => 'Club ID is required'
        ], 400);
    }
    $club = Clubs::select('terms_&_conditions', 'joining_fee')
        ->where('id', $club_id)
        ->first();
    if (!$club) {
        return response()->json([
            'status' => 'error',
            'message' => 'Club ID does not exist'
        ], 404);
    }
    return response()->json([
        'status' => 'success',
        'data' => $club
    ], 200);
}


public function getPendingRequests(Request $request)
{
    try {
        $club_id = $request->query('id');
        if (!$club_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Club ID is required'
            ], 400);
        }
        $pendingRequests = ClubMembers::join('users', 'club_members.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'users.email',
                'users.profile_image',
                'club_members.role',
                'club_members.created_at as requested_at'
            )
            ->where('club_members.club_id', $club_id)
            ->where('club_members.request_approved', false)
            ->orderBy('club_members.created_at', 'desc')
            ->get();
        foreach ($pendingRequests as $request) {
            if (!empty($request->profile_image)) {
                $request->profile_image = Storage::disk('public')->url('uploads/' . $request->profile_image);
            }
        }
        return response()->json([
            'status' => 'success',
            'data' => [
                'pending_requests' => $pendingRequests,
            ]
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function boostPigeon(Request $request)
{
    try {
        $validated = $request->validate([
            'pigeon_id' => 'required|exists:pigeons,id',
            'payment_image' => 'required',
        ]);
        $user = Auth::user();
        $userName = $user->name;
        $fileName = "";
        if ($request->has('payment_image') && $request->payment_image) {
            $file = base64_decode($request->payment_image);
            if ($file === false) {
                return response()->json(['error' => 'Invalid base64 image data'], 400);
            }
            $fileName = time() . '.png';
            Storage::disk('public')->put('uploads/' . $fileName, $file);
        }
        
        $boostStart = now();
        $boostEnd = now(); // Setting boost end to now (no fixed end date)

        $boostData = [
            [
                'user_id' => 58, 
                'user_name' => $userName,
                'pigeon_id' => $validated['pigeon_id'],
                'payment_image' => $fileName,
                'boost_start' => $boostStart,
                'boost_end' => $boostEnd,
            ],
            [
                'user_id' => 59, 
                'user_name' => $userName,
                'pigeon_id' => $validated['pigeon_id'],
                'payment_image' => $fileName,
                'boost_start' => $boostStart,
                'boost_end' => $boostEnd,
            ],
        ];
        Boost::insert($boostData); 
        return response()->json([
            'message' => 'Pigeon boosted successfully!',
            'status' => 'success',
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => $e->errors()], 422);
    } catch (\Exception $e) {
        return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}

public function getBoosts(Request $request)
{
    try {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Get boost records for the user while excluding boosts that have an approved boost_end
        $boosts = Boost::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotIn('id', function ($subQuery) {
                    $subQuery->select('id')
                        ->from('boosts')
                        ->whereIn('user_id', [58, 59])
                        ->where('boost_end', '>', now()); // Exclude records with approved boost_end
                });
            })
            ->with('pigeon') 
            ->orderByDesc('created_at')
            ->get();

        foreach ($boosts as $boost) {
            // Process the pigeon's images
            if (!empty($boost->pigeon) && !empty($boost->pigeon->images)) {
                // Check if images is already an array
                if (is_array($boost->pigeon->images)) {
                    $boost->pigeon->images = array_map(function ($image) {
                        return url('storage/uploads/' . $image); // Generate full URLs for pigeon images
                    }, $boost->pigeon->images);
                } else {
                    $decodedImages = json_decode($boost->pigeon->images, true);
                    if (is_array($decodedImages)) {
                        $boost->pigeon->images = array_map(function ($image) {
                            return url('storage/uploads/' . $image); // Generate full URLs for pigeon images
                        }, $decodedImages);
                    } else {
                        $boost->pigeon->images = []; // If decoding fails, set to empty array
                    }
                }
            } else {
                $boost->pigeon->images = []; // If no images, set to empty array
            }

            // Keep payment_image in its current place and fetch the URL
            if (!empty($boost->payment_image)) {
                $boost->payment_image = url('storage/uploads/' . $boost->payment_image); // Generate full URL for payment image
            }
        }

        return response()->json([
            'status' => 'success',
            'boosts' => $boosts,
        ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error fetching boosts: ' . $e->getMessage());
        return response()->json([
            'error' => 'An unexpected error occurred: ' . $e->getMessage(),
        ], 500);
    }
}


public function approveBoost(Request $request)
{
    try {
        // Validate that pigeon_id is provided in the request body
        $validated = $request->validate([
            'pigeon_id' => 'required|exists:pigeons,id',
        ]);

        $pigeonId = $validated['pigeon_id'];
        $boostEnd = now()->addDays(2);

        // Step 1: Find and update the boost for user 58 or 59
        $boost = Boost::where('pigeon_id', $pigeonId)
            ->whereIn('user_id', [58, 59])
            ->first();

        if (!$boost) {
            return response()->json(['message' => 'Boost record not found for the pigeon'], 404);
        }

        $boost->boost_end = $boostEnd;
        $boost->save();

        // Step 2: Delete all other boosts for this pigeon_id except user_id 58 and 59
        Boost::where('pigeon_id', $pigeonId)
            ->delete();

        // Step 3: Update created_at for visibility purposes
        Pigeons::where('id', $pigeonId)->update(['created_at' => now()]);

        return response()->json([
            'message' => 'Pigeon boost approved.',
            'status' => 'success',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['error' => $e->errors()], 422);
    } catch (\Exception $e) {
        return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
    }
}




public function reportPost(Request $request)
{
    try {
        $validated = $request->validate([
            'pigeon_id' => 'required|exists:pigeons,id',
            'reason_for_report' => 'required|string|max:255',
            'detail' => 'required|string',
        ]);
        $pigeon = pigeons::findOrFail($validated['pigeon_id']);  // Fetch the pigeon to validate its existence
        $reportData = [
            [
                'pigeon_id' => $pigeon->id,
                'user_id' => 58, // Assign to user ID 58
                'reason_for_report' => $validated['reason_for_report'],
                'detail' => $validated['detail'],
            ],
            [
                'pigeon_id' => $pigeon->id,
                'user_id' => 59, // Assign to user ID 59
                'reason_for_report' => $validated['reason_for_report'],
                'detail' => $validated['detail'],
            ],
        ];
        Report::insert($reportData); // Bulk insert reports
        return response()->json(['message' => 'Report submitted successfully','status' => 'success'], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['message' => 'Pigeon not found'], 404);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        return response()->json(['message' => 'An error occurred while processing the request', 'error' => $e->getMessage()], 500);
    }
}


public function getReports(Request $request)
{
    try {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        $reports = Report::where('user_id', $user->id)
            ->with('pigeon')
            ->orderByDesc('created_at')
            ->get();

        foreach ($reports as $report) {
            if (!empty($report->pigeon) && !empty($report->pigeon->images)) {
                $images = $report->pigeon->images;

                // Decode if JSON string, use directly if array
                if (is_string($images)) {
                    $decodedImages = json_decode($images, true);
                } elseif (is_array($images)) {
                    $decodedImages = $images;
                } else {
                    $decodedImages = [];
                }

                // Make sure it's an array of image paths/URLs
                if (is_array($decodedImages)) {
                    $report->pigeon->images = array_map(function ($image) {
                        // Check if it's already a full URL
                        if (filter_var($image, FILTER_VALIDATE_URL)) {
                            return $image;
                        }
                        return url('storage/uploads/' . ltrim($image, '/'));
                    }, $decodedImages);
                } else {
                    $report->pigeon->images = [];
                }
            } else {
                $report->pigeon->images = [];
            }
        }

        return response()->json([
            'status' => 'success',
            'reports' => $reports,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while processing the request',
            'error' => $e->getMessage(),
        ], 500);
    }
}






public function addReview(Request $request)
{
    try {
        // Validate incoming request data
        $validatedData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'shop_id' => 'required|exists:shops,id',
            'reason' => 'required|string|max:1000', // Single reason at a time
        ]);
        $username = Auth::user()->name; // Get the current user's name
        // Fetch an existing review for the given product_id and shop_id
        $review = Review::where('product_id', $validatedData['product_id'])
            ->where('shop_id', $validatedData['shop_id'])
            ->first();
        if ($review) {
            $existingReasons = json_decode($review->ReasonForReview, true) ?: []; // Decode existing reasons from JSON to array
            // Find if the user already exists in the review reasons
            $userReasonIndex = collect($existingReasons)->search(function ($reasonData) use ($username) {
                return $reasonData['name'] === $username;
            });
            if ($userReasonIndex !== false) {
                $existingReasons[$userReasonIndex]['reasons'][] = $validatedData['reason']; // If user exists, append the new reason to their existing reasons
                $existingReasons[$userReasonIndex]['reasons'] = array_unique($existingReasons[$userReasonIndex]['reasons']);
            } else { 
                $existingReasons[] = [
                    'name' => $username,
                    'reasons' => [$validatedData['reason']],
                ];
            }
            $review->ReasonForReview = json_encode($existingReasons);  // Save the updated reasons back to the database
            $review->save();
        } else {
            // Create a new review entry with the current user's reason
            Review::create([
                'product_id' => $validatedData['product_id'],
                'shop_id' => $validatedData['shop_id'],
                'ReasonForReview' => json_encode([
                    [
                        'name' => $username,
                        'reasons' => [$validatedData['reason']],
                    ]
                ]),
                'AddedBy' => $username,
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Review added successfully!',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error adding review.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function getWalkingUserProfileView(Request $request)
{
    try {
        // Validate the request
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id query parameter is required'], 400);
        }

        // Fetch user data
        $user = User::select('name', 'email', 'phone', 'address', 'created_at as member_since')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Fetch followers and following counts
        $followersCount = Followers::where('followed_by', $userId)->count();
        $followingCount = Followers::where('following', $userId)->count();

        // Fetch pigeons
        $pigeons = Pigeons::where('user_id', $userId)->get();

        foreach ($pigeons as $pigeon) {
            // Decode and format pigeon images
            if (!empty($pigeon->images)) {
                $pigeon->images = json_decode($pigeon->images, true);
                $pigeon->images = array_map(function ($image) {
                    return Storage::disk('public')->url('uploads/' . $image);
                }, $pigeon->images);
            }
        }

        // Fetch shop data
        $shops = Shops::select('id', 'shop_name', 'image as shop_image', 'created_at as member_since')
            ->where('user_id', $userId)
            ->get();

        foreach ($shops as $shop) {
            // Format shop image URLs
            if (!empty($shop->shop_image)) {
                $shop->shop_image = Storage::disk('public')->url('uploads/' . $shop->shop_image);
            }
        }

        // Fetch club IDs for the user
        $clubIds = ClubMembers::where('user_id', $userId)->pluck('club_id');

        // Fetch club details for the user
        $clubs = Clubs::whereIn('id', $clubIds)->get();

        // Fetch user's club score dynamically
        $clubScore = (int) ClubScore::where('user_id', $userId)->sum('club_score');

        // Add fields to each club (keeping hardcoded values for club_score in club)
        $clubs = $clubs->map(function ($club) {
            $club->club_score = 15; // Keep club score hardcoded to 15
            $club->club_rank = 15;
            $club->rating = 4;

            if (!empty($club->club_image)) {
                // Fetch the full URL for the club image
                $club->club_image = Storage::disk('public')->url('uploads/' . $club->club_image);
            }

            return $club;
        });

        // Structure the response data
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'followers' => $followersCount,
            'following' => $followingCount,
            'club_score' => $clubScore,
            'post' => $pigeons,
            'shop' => $shops,
            'club' => $clubs,
        ];

        return response()->json(['status' => 'success', 'data' => $data], 200);

    } catch (\Exception $e) {
        // Log the exception for debugging
        \Log::error('Error fetching user profile: ' . $e->getMessage());

        // Return a generic error message
        return response()->json(['error' => 'An unexpected error occurred. Please try again later.'], 500);
    }
}



public function viewsOnArticle(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'article_id' => 'required|exists:articles,id',
        ]);

        // Get authenticated user
        $user = auth()->user(); // This requires the auth:sanctum middleware
        $userId = $user->id;

        // Check if the user has already viewed the article
        $action = DB::table('article_user_actions')
            ->where('user_id', $userId)
            ->where('article_id', $request->article_id)
            ->first();

        if ($action && $action->viewed) {
            return response()->json([
                'status' => 'success',
                'message' => 'View already recorded.',
            ]);
        }

        // Add or update the user's action
        if (!$action) {
            DB::table('article_user_actions')->insert([
                'user_id' => $userId,
                'article_id' => $request->article_id,
                'viewed' => 1,
                'liked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('article_user_actions')
                ->where('user_id', $userId)
                ->where('article_id', $request->article_id)
                ->update(['viewed' => 1, 'updated_at' => now()]);
        }

        // Increment the views column in the articles table
        DB::table('articles')->where('id', $request->article_id)->increment('views');

        return response()->json([
            'status' => 'success',
            'message' => 'View count updated successfully.',
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in viewsOnArticle API: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'An internal server error occurred.',
        ], 500);
    }
}


public function likesOnArticle(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'article_id' => 'required|exists:articles,id',
        ]);

        // Get the authenticated user
        $user = auth()->user(); // This requires the auth:sanctum middleware
        $userId = $user->id;

        // Check if the user has already liked the article
        $action = DB::table('article_user_actions')
            ->where('user_id', $userId)
            ->where('article_id', $request->article_id)
            ->first();

        if ($action && $action->liked) {
            return response()->json([
                'status' => 'success',
                'message' => 'Like already recorded.',
            ]);
        }

        // Add or update the user's action
        if (!$action) {
            DB::table('article_user_actions')->insert([
                'user_id' => $userId,
                'article_id' => $request->article_id,
                'viewed' => 0,
                'liked' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('article_user_actions')
                ->where('user_id', $userId)
                ->where('article_id', $request->article_id)
                ->update(['liked' => 1, 'updated_at' => now()]);
        }

        // Increment the likes column in the articles table
        DB::table('articles')->where('id', $request->article_id)->increment('likes');

        return response()->json([
            'status' => 'success',
            'message' => 'Like count updated successfully.',
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in likesOnArticle API: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'An internal server error occurred.',
        ], 500);
    }
}


 public function commentsOnArticle(Request $request)
{
    try {
        $request->validate([
            'article_id' => 'required|exists:articles,id',
            'comment' => 'required|string|max:1000',
        ]);

        // Attempt to create the comment
        $comment = Comment::create([
            'article_id' => $request->article_id,
            'comment' => $request->comment,
        ]);

        // Increment the comments_count for the article in both the table and model
        $article = Articles::find($request->article_id);
        if ($article) {
            // Increment the comments_count column in the database
            $article->increment('comments_count'); // Make sure this column exists in the 'articles' table
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Comment added successfully.',
        ]);
    } catch (\Exception $e) {
        // Log the error details
        Log::error('Error in commentsOnArticle: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to add comment.',
            'error' => $e->getMessage(),
        ], 500);
    }
}


//$user = Auth::user(); $user->id,
    public function sendMessage(Request $request, AblyService $ablyService)
    {
        try {
            $request->validate([
                'club_id' => 'required|exists:clubs,id',
                'message' => 'nullable|string|max:1000',
                'image' => 'nullable|string',
                'voice_message' => 'nullable|string',
                'video' => 'nullable|string',
            ]);

            $messageData = [
                'sender_id' => Auth::id(),
                'club_id' => $request->club_id,
                'message' => $request->message,
            ];

            if ($request->image) {
                $imageData = base64_decode($request->image);
                $fileName = time() . '.png';
                Storage::disk('public')->put('uploads/' . $fileName, $imageData);
                $messageData['image'] = $fileName; 
            }

            if ($request->voice_message) {
                $voiceMessageData = base64_decode($request->voice_message);
                $fileName = time() . '.mp3';
                Storage::disk('public')->put('uploads/' . $fileName, $voiceMessageData);
                $messageData['voice_message'] = $fileName; 
            }

            if ($request->video) {
                $videoData = base64_decode($request->video);
                $fileName = time() . '.mp4';
                Storage::disk('public')->put('uploads/' . $fileName, $videoData);
                $messageData['video'] = $fileName; 
            }

            $message = Message::create($messageData); 

            \Log::info('Message created successfully:', ['message' => $message]);

            $channelName = 'club-' . $request->club_id;
            $ablyService->publish($channelName, $message); 

            return response()->json(['status' => 'success', 'message' => 'Message sent successfully'], 200);

        } catch (Throwable $e) {
            \Log::error('Error in sendMessage:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Something went wrong!'], 500);
        }
    }
    
    public function getMessages(Request $request)
{
    try {
        $messages = Message::select('id', 'sender_id', 'club_id', 'message', 'image', 'voice_messages', 'videos', 'created_at')
            ->with(['sender' => function ($query) {
                $query->select('id', 'name', 'profile_image');
            }])
            ->where('club_id', $request->club_id)
            ->where('created_at', '>=', now()->subDays(30)) // Fetch messages from the last 30 days
            ->latest()
            ->get();

        $messages = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->name ?? null,
                'club_id' => $message->club_id,
                'message' => $message->message,
                'image' => $message->image ? Storage::disk('public')->url('uploads/' . $message->image) : null,
                'voice_messages' => $message->voice_messages ? Storage::disk('public')->url('uploads/' . $message->voice_messages) : null,
                'videos' => $message->videos ? Storage::disk('public')->url('uploads/' . $message->videos) : null,
                'created_at' => $message->created_at,
                'profile_image' => !empty($message->sender->profile_image)
                    ? Storage::disk('public')->url('uploads/' . $message->sender->profile_image)
                    : null,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $messages], 200);
    } catch (\Exception $e) {
        \Log::error('Error fetching messages:', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Something went wrong while fetching messages!'], 500);
    }
}


public function deletePigeon(Request $request)
    {
        $pigeon_id = $request->query('pigeon_id');
        if (!$pigeon_id) {
            return response()->json(['message' => 'pigeon_id is required'], 400);
        }
        $pigeon = Pigeons::find($pigeon_id);
        if (!$pigeon) {
            return response()->json(['message' => 'Pigeon not found'], 404);
        }
        $pigeon->delete();
        return response()->json(['message' => 'Pigeon deleted successfully'], 200);
    }

    public function createNotification(Request $request)
    {
        try {
            $user = $request->user(); // Get authenticated user
    
            // Validate the request
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'user_name' => 'required|string|max:100',
            ]);
    
            // Create a new notification
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $validatedData['title'],
                'user_name' => $validatedData['user_name'],
                'is_read' => 0, // Default value
            ]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Notification created successfully.',
                'data' => $notification,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
   public function markAsRead(Request $request)
{
    try {
        // Validate the request data
        $validatedData = $request->validate([
            'id' => 'required|exists:notifications,id', // Validate the notification ID
            'is_read' => 'required|boolean', // Validate is_read value (0 or 1)
        ]);

        // Find the notification by ID
        $notification = Notification::where('id', $validatedData['id'])->firstOrFail();

        // Update the notification's read status
        $notification->is_read = $validatedData['is_read'];
        $notification->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification read status updated successfully.',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update notification read status.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



public function getUnreadNotifications(Request $request)
{
    try {
        // Fetch all unread notifications (is_read = 0)
        $unreadNotifications = Notification::where('is_read', 0) 
                                ->orderByDesc('created_at')
                                           ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Unread notifications retrieved successfully.',
            'data' => $unreadNotifications,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve unread notifications.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    
    public function getPigeonNames()
{
    try {
        $pigeons = DB::table('PigeonNames')->get();
        return response()->json(['status' => 'success', 'data' => $pigeons], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    }
}

    public function addClubScore(Request $request)
{
    try {
        // Validate request data
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'club_score' => 'required|integer',
        ]);

        // Create club score record
        $clubScore = ClubScore::create([
            'user_id' => $request->user_id,
            'club_score' => $request->club_score,
        ]);

        return response()->json([
            'status'=>'success',
            'message' => 'Club score added successfully',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong!',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function deleteUser(Request $request)
{
    try {
        $user = auth()->user(); 
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        $user->tokens()->delete(); 
        $user->delete(); 

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.'
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function updateProfileImage(Request $request) {
    try {
        $user = auth()->user(); // Get the authenticated user

        $validator = Validator::make($request->all(), [
            'profile_image' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $fileName = "";
        if ($request->has('profile_image')) {
            $file = base64_decode($request->profile_image);
            $fileName = time() . '.png';
            Storage::disk('public')->put('uploads/' . $fileName, $file);

            if (!empty($user->profile_image) && Storage::disk('public')->exists('uploads/' . $user->profile_image)) {
                Storage::disk('public')->delete('uploads/' . $user->profile_image);
            }

            $user->update(['profile_image' => $fileName]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile image updated successfully!',
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function getUserNames()
{
    try {
        $users = User::select('id', 'name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $users, 
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}


public function verifyOtp(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        }

        $record = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$record) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired OTP'], 400);
        }

        // Update the user's email_verified_at field
        User::where('email', $request->email)->update([
            'email_verified_at' => Carbon::now(),
        ]);

        // Optionally delete the OTP
        DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json(['status' => 'success', 'message' => 'OTP verified, email verified successfully'], 200);

    } catch (Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}






}
