@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ __('You are logged in!') }}
                    <br>
                        <br>
                        <br>
                        <br>

{{--                    <a href="{{url("export-pdf")}}" class="btn btn-primary" id="download-cert">Download Certificate</a>--}}

                    {{--  Upload docx certificate template --}}
                    <form action="{{route('upload-template')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="template">Upload Certificate Template</label>
                            <input type="file" name="template" id="template" class="form-control">
                            @error('template')
                            <span class="text-danger">{{$message}}</span>
                            @enderror
                            <button type="submit" class="btn btn-primary mt-3">Upload</button>
                            <a href="{{route('download-template')}}" class="btn btn-success mt-3">Download Template</a>
                        </div>
                    </form>
                        <br>
                        <br>
                        <br>
                        @if(session('success'))
                            <div class="alert alert-success">{{session('success')}}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger">{{session('error')}}</div>
                        @endif
                        {{--  Upload docx certificate users excel --}}
                        <form action="{{route('upload-sheet')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="users">Upload Users Excel</label>
                                <input type="file" name="sheet" id="sheet" class="form-control">
                                @error('sheet')
                                <span class="text-danger">{{$message}}</span>
                                @enderror
                                <button type="submit" class="btn btn-primary mt-3">Upload</button>
                                <a href="{{route('download-sheet')}}" class="btn btn-success mt-3">Download Users Excel</a>
                            </div>
                        </form>
                    </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>

  <!-- <script>
    const export2Pdf = async () => {
      let printHideClass = document.querySelectorAll('.print-hide');
      printHideClass.forEach(item => item.style.display = 'none');
      await fetch('{{url("export-pdf")}}', {
        method: 'GET'
      }).then(response => {
        if (response.ok) {
          response.json().then(response => {
            var link = document.createElement('a');
            link.href = response;
            link.click();
            printHideClass.forEach(item => item.style.display='');
          });
        }
      }).catch(error => console.log(error));
    }
  </script>
  <script> document.getElementById('download-cert').addEventListener('click', export2Pdf); </script> -->
@endsection
