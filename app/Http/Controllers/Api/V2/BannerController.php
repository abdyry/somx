<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\LinkedWebsite;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(
        private Banner $banner,
        private LinkedWebsite $linked_website
    ){}

    /**
     * @param Request $request
     * @return mixed
     */
    public function get_customer_banner(Request $request): mixed
    {
        $banners = $this->banner->select('title', 'image', 'url', 'receiver')->customerAndAll()->active()->get();
        return $banners;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function get_agent_banner(Request $request): mixed
    {
        $banners = $this->banner->select('title', 'image', 'url', 'receiver')->agentAndAll()->active()->get();
        return $banners;
    }
    
     public function linked_website(Request $request): mixed
    {
        $linked_websites = $this->linked_website->select('name', 'image', 'url')->active()->orderBy("id", "desc")->take(20)->get();
        return $linked_websites;
    }
}
