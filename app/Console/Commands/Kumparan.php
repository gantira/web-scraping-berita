<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Spider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Console\News;

class Kumparan extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:kumparan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Viva';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $data=$this->getArticles();\
        usort($data, function($a, $b) {
            $sortby = 'date';
            return strcmp(@$b[$sortby], @$a[$sortby]);
        });
        dump($data);
        
        News::saveLatest('kumparan', $data);
        News::saveDaily('kumparan', $data);
    }

    public function getArticles() {
        $cmd = "curl 'https://graphql-v4.kumparan.com/query' -H 'origin: https://kumparan.com' -H 'accept-encoding: gzip, deflate, br' -H 'accept-language: en-US,en;q=0.9,id;q=0.8' -H 'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.96 Safari/537.36' -H 'content-type: text/plain' -H 'accept: */*' -H 'referer: https://kumparan.com/@kumparanbisnis' -H 'authority: graphql-v4.kumparan.com' --data-binary '".'[{"operationName":"FindStoryFeedByPublisherID","variables":{"publisherID":"1544003652","cursor":"1","cursorType":"PAGE","size":10,"clientType":"WEB"},"query":"query FindStoryFeedByPublisherID($publisherID: ID!, $cursor: String!, $cursorType: CursorType!, $size: Int!, $clientType: ClientType) {\n  FindStoryFeedByPublisherID(publisherID: $publisherID, cursor: $cursor, cursorType: $cursorType, size: $size, clientType: $clientType) {\n    ...StoryCursor\n    __typename\n  }\n}\n\nfragment StoryCursor on StoryCursor {\n  edges {\n    ...Story\n    __typename\n  }\n  cursorInfo {\n    ...CursorInfo\n    __typename\n  }\n  __typename\n}\n\nfragment Story on Story {\n  __typename\n  id\n  authorID\n  title\n  createdAt\n  updatedAt\n  deletedAt\n  publishedAt\n  source\n  isAgeRestrictedContent\n  slug\n  status\n  leadText\n  publisherID\n  publishedRevisionID\n  draftRevisionID\n  metaDescription\n  metaKeyword\n  customTrackerImpressionURL\n  customTrackerScript\n  sponsorID\n  locationName\n  locationLat\n  locationLon\n  internalCreatorID\n  lastUpdatedBy\n  isCleanView\n  isStickyStory\n  sponsor {\n    ...Sponsor\n    __typename\n  }\n  author {\n    ...User\n    __typename\n  }\n  publisher {\n    ...Publisher\n    __typename\n  }\n  editors {\n    ...User\n    __typename\n  }\n  reporters {\n    ...User\n    __typename\n  }\n  headline {\n    ...Headline\n    __typename\n  }\n  storyAddOns {\n    ...StoryAddOn\n    __typename\n  }\n  contentPublish {\n    ...Document\n    __typename\n  }\n  contentDraft {\n    ...Document\n    __typename\n  }\n  leadMedia {\n    ...Media\n    __typename\n  }\n  topics {\n    ...Topic\n    __typename\n  }\n}\n\nfragment Media on Media {\n  id\n  title\n  description\n  publicID\n  externalURL\n  awsS3Key\n  height\n  width\n  locationName\n  locationLat\n  locationLon\n  mediaType\n  mediaSourceID\n  photographer\n  eventDate\n  __typename\n}\n\nfragment Topic on Topic {\n  __typename\n  id\n  name\n  slug\n  description\n  isActive\n  isPrivate\n  isSpecial\n  isShowTopicCover\n  coverMedia {\n    ...Media\n    __typename\n  }\n}\n\nfragment Sponsor on Sponsor {\n  id\n  name\n  description\n  url\n  media {\n    ...Media\n    __typename\n  }\n  __typename\n}\n\nfragment User on User {\n  __typename\n  id\n  name\n  username\n  aboutMe\n  email\n  phone\n  emailVerified\n  phoneVerified\n  profilePictureMedia {\n    ...Media\n    __typename\n  }\n  coverPictureMedia {\n    ...Media\n    __typename\n  }\n  gender\n  userStatus: status\n  birthDate\n  isRecommended\n  createdAt\n  updatedAt\n  deletedAt\n  aboutMe\n  isVerified\n  websiteURL\n  isSelf\n}\n\nfragment Publisher on Publisher {\n  __typename\n  id\n  name\n  slug\n  description\n  website\n  isVerified\n  isActive\n  coverMedia {\n    ...Media\n    __typename\n  }\n  avatarMedia {\n    ...Media\n    __typename\n  }\n  publisherGroupID\n}\n\nfragment Headline on Headline {\n  storyID\n  desktopMedia {\n    ...Media\n    __typename\n  }\n  mobileMedia {\n    ...Media\n    __typename\n  }\n  startTime\n  endTime\n  __typename\n}\n\nfragment StoryAddOn on StoryAddOn {\n  object {\n    __typename\n    ... on Polling {\n      ...Polling\n      __typename\n    }\n    ... on Gallery {\n      ...Gallery\n      __typename\n    }\n  }\n  addOnType\n  __typename\n}\n\nfragment Polling on Polling {\n  __typename\n  id\n  name\n  description\n  mediaID\n  startsAt\n  endsAt\n  questions {\n    ...Question\n    __typename\n  }\n}\n\nfragment Question on Question {\n  id\n  pollingID\n  detail\n  position\n  choices {\n    ...Choice\n    __typename\n  }\n  __typename\n}\n\nfragment Choice on Choice {\n  id\n  questionID\n  detail\n  mediaID\n  position\n  stats\n  __typename\n}\n\nfragment Gallery on Gallery {\n  __typename\n  id\n  name\n  description\n  galleryMedias {\n    ...GalleryMedia\n    __typename\n  }\n}\n\nfragment GalleryMedia on GalleryMedia {\n  mediaID\n  caption\n  description\n  media {\n    ...Media\n    __typename\n  }\n  __typename\n}\n\nfragment Document on Document {\n  id\n  document\n  type\n  __typename\n}\n\nfragment CursorInfo on CursorInfo {\n  size\n  count\n  countPage\n  hasMore\n  cursor\n  cursorType\n  nextCursor\n  __typename\n}\n"}]'."' --compressed";
        // dd($cmd);
        $output=exec($cmd);
        if($output) {
            $decode=json_decode($output,true);
        }
        $edges=$decode[0]['data']['FindStoryFeedByPublisherID']['edges'];
        //dd($edges);
        foreach($edges as $e) {
            //$slate=$e['slate'];
            $document = '';
            $row['title'] = $e['title'];
            $row['url'] = 'https://kumparan.com/@kumparanbisnis/'.$e['slug'];
            $raw = Spider::staticRequest($row['url']);
            //dump($raw->request->getStatusCode());
            if($raw->request->getStatusCode() == 200){
                $row['md5url']=md5($row['url']);
                $image=$e['leadMedia'][0]['externalURL'];
                $image_raw=explode("/upload/", $image);
                $thumbnail='https://blue.kumparan.com/kumpar/image/upload/fl_progressive,fl_lossy,c_fill,q_auto:best,w_200,ar_16:9/'.$image_raw[1];
                $row['thumbnail']=$thumbnail;
                $row['image']=$image;
                $row['source']='kumparan';
                $row['section']='bisnis';
                $decode_doc=json_decode($e['contentPublish']['document'],true);
                $nodes = $decode_doc['document']['nodes'];
                foreach ($nodes as $n) {
                    if($n['type'] == 'paragraph'){
                        foreach ($n['nodes'] as $nchild) {
                            if($nchild['object'] == 'text'){
                                foreach ($nchild['leaves'] as $nleaf) {
                                    $document .= $nleaf['text'];
                                }
                            }else if($nchild['object'] == 'inline'){
                                $document .= $nchild['nodes'][0]['leaves'][0]['text'];
                            }
                        }
                        $document .= PHP_EOL;
                    }
                }
                $row['content']=$document;
                //dump($row['content']);
                $row['raw_date']=$e['publishedAt'];
                //dump($row['raw_date']);
                $date = new \Illuminate\Support\Carbon($row['raw_date']);
                $date->setTimezone('Asia/Jakarta');
                $row['date']=$date->toW3cString();;
            }
            $data[] = $row;
        }
        return $data;
    }
}
