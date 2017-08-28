<li>
    <a href="{{route('home.show',array('id'=>$project_id))}}" class="box" title="{{$project_name}}" target="_blank">
        @if(empty($project_cover))
            <img src="/static/images/book.jpg" class="cover"  style="height:230px;" alt="{{$project_name}}-{{$project_author}}">
        @else
            <img src="{{$project_cover}}" class="cover"  style="height:230px;" alt="{{$project_name}}-{{$project_author}}">
        @endif
        <h4 style="height: auto;white-space:nowrap;">{{$project_name}}</h4>
        <span style="white-space: nowrap;">作者：{{$project_author}}  {{substr($project_date, 0, 10)}}</span>
    </a>
    <!--
    <p class="summary hidden-xs hidden-sm hidden-md">
        <a href="{{route('home.show',array('id'=>$project_id))}}" class="text" target="_blank">
            {{$description}}
        </a>
    </p>
    -->
</li>