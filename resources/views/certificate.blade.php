<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ASFEC</title>
   <style>
       /*
* Prefixed by https://autoprefixer.github.io
* PostCSS: v8.4.14,
* Autoprefixer: v10.4.7
* Browsers: last 4 version
*/

       @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&family=Exo+2:ital,wght@0,100..900;1,100..900&display=swap');

       @page{
           size:A4 landscape;
           margin:0;
           padding:0
       }

       body {
           margin:0;
           padding:0;
           font-family: cairo;
           line-height:2.5rem;
       }

       .left-aside {
           float: left;
           margin:0;
           padding:0;
           position: relative;
           width: 74mm;
           height:210mm;
           background:-webkit-gradient( linear, left top, left bottom, color-stop(100%, rgba(0, 0, 0, 0.5)), to(rgba(0, 0, 0, 0.7))),url("data:image/svg+xml,%3Csvg width='48' height='64' viewBox='0 0 48 64' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M48 28v-4L36 12 24 24 12 12 0 24v4l4 4-4 4v4l12 12 12-12 12 12 12-12v-4l-4-4 4-4zM8 32l-6-6 10-10 10 10-6 6 6 6-10 10L2 38l6-6zm12 0l4-4 4 4-4 4-4-4zm12 0l-6-6 10-10 10 10-6 6 6 6-10 10-10-10 6-6zM0 16L10 6 4 0h4l4 4 4-4h4l-6 6 10 10L34 6l-6-6h4l4 4 4-4h4l-6 6 10 10v4L36 8 24 20 12 8 0 20v-4zm0 32l10 10-6 6h4l4-4 4 4h4l-6-6 10-10 10 10-6 6h4l4-4 4 4h4l-6-6 10-10v-4L36 56 24 44 12 56 0 44v4z' fill='gray' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
           background:-o-linear-gradient( rgba(0, 0, 0, 0.5) 100%, rgba(0, 0, 0, 0.7)100%),url("data:image/svg+xml,%3Csvg width='48' height='64' viewBox='0 0 48 64' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M48 28v-4L36 12 24 24 12 12 0 24v4l4 4-4 4v4l12 12 12-12 12 12 12-12v-4l-4-4 4-4zM8 32l-6-6 10-10 10 10-6 6 6 6-10 10L2 38l6-6zm12 0l4-4 4 4-4 4-4-4zm12 0l-6-6 10-10 10 10-6 6 6 6-10 10-10-10 6-6zM0 16L10 6 4 0h4l4 4 4-4h4l-6 6 10 10L34 6l-6-6h4l4 4 4-4h4l-6 6 10 10v4L36 8 24 20 12 8 0 20v-4zm0 32l10 10-6 6h4l4-4 4 4h4l-6-6 10-10 10 10-6 6h4l4-4 4 4h4l-6-6 10-10v-4L36 56 24 44 12 56 0 44v4z' fill='gray' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
           background:linear-gradient( rgba(0, 0, 0, 0.5) 100%, rgba(0, 0, 0, 0.7)100%),url("data:image/svg+xml,%3Csvg width='48' height='64' viewBox='0 0 48 64' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M48 28v-4L36 12 24 24 12 12 0 24v4l4 4-4 4v4l12 12 12-12 12 12 12-12v-4l-4-4 4-4zM8 32l-6-6 10-10 10 10-6 6 6 6-10 10L2 38l6-6zm12 0l4-4 4 4-4 4-4-4zm12 0l-6-6 10-10 10 10-6 6 6 6-10 10-10-10 6-6zM0 16L10 6 4 0h4l4 4 4-4h4l-6 6 10 10L34 6l-6-6h4l4 4 4-4h4l-6 6 10 10v4L36 8 24 20 12 8 0 20v-4zm0 32l10 10-6 6h4l4-4 4 4h4l-6-6 10-10 10 10-6 6h4l4-4 4 4h4l-6-6 10-10v-4L36 56 24 44 12 56 0 44v4z' fill='gray' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
       }

       .left-aside:after {
           content: "";
           position: absolute;
           left:100%;
           width:10mm;
           height:210mm;
           background-color: #ea980f;
       }

       .left-aside .decoration {
           position: absolute;
           -webkit-transform: translateY(-50%);
           -ms-transform: translateY(-50%);
           transform: translateY(-50%);
           top:50%;
           left: 6mm;
       }

       .content {
           float: left;
           margin-top: 4mm;
           width: 200mm;
           margin-left: 15mm;
           text-align: center;
       }

       .content .content-logos {
           position: absolute;
       }

       .content .right-logo {
           position:absolute;
           left:85%;
       }

       .content h1 {
           margin-top: 80px;
       }

       .content h1, .content .person-name {
           color: #a37430;
           font-size: 40px;
           font-weight: bold;
       }

       .content .title {
           font-size: 30px;
           color: #545454;
           font-weight: bold;
       }

       .content .sub-title, .content .date {
           font-size: 25px;
       }

       .content .sub-title {
           line-height: 2.2rem;
           font-size: 23px;
       }

       .content-footer div:first-child {
           position: absolute;
           top:83%;
           font-size:25px;
           line-height: 1.5rem;
           color: #545454;
           font-weight: bold;
       }

       .content-footer div:nth-child(2) {
           position: absolute;
           font-size:25px;
           top:79%;
           left: 80%;
           line-height: 1.2rem;
           color: #545454;
           margin-top:2mm;
           font-weight: bold;
       }
   </style>
</head>

<body>
<div class="container">
    <div class="left-aside">
        <img class="decoration" src="https://firebasestorage.googleapis.com/v0/b/yala-t7ady.appspot.com/o/decoration.png?alt=media&token=73ab117a-bb01-44aa-be54-9d4e2f6b88ba">
    </div>
    <div class="content">
        <div class="content-logos">
            <img src="https://firebasestorage.googleapis.com/v0/b/yala-t7ady.appspot.com/o/images.png?alt=media&token=3121355a-e7d7-4a6d-b634-5a5eec480610" />
        </div>
        <img class="right-logo" src="https://firebasestorage.googleapis.com/v0/b/yala-t7ady.appspot.com/o/Screenshot%20from%202024-04-03%2022-26-18.png?alt=media&token=f9c1ebd8-8dde-49b3-a2cf-bac4c41e1f6e" />
        <h1>شهادة شكر وتقدير</h1>
        <p class="title">يتقدم المركز الإقليمي لتعليم الكبار – أسفك سرس الليان </p>
        <p class="title">بأسمى آيات الشكر والتقدير والإمتنان إلى</p>
        <p class="person-name"> الدكتور/ة: {{$real_name}}</p>
        <p class="title">على مشاركتكم الفعالة بالورشة الإقليمية</p>
        <p class="sub-title">التحركات السكانية وأثرها على نظم تعليم الكبار في المنطقة العربية</p>
        <p class="sub-title">بورقة عمل بعنوان دعم الاحتياجات التعليمية للمهاجرين والنازحين في إطار المعونة الإنسانية والإنمائية</p>
        <p class="date">الأحد الموافق (24-17-10) مارس- 2024م</p>
        <div class="content-footer">
            <div>
                <p>مدير المركز</p>
                <p class="center-head">د/ محمد عبد الوارث القاضي</p>
            </div>
            <div>
                <p>الإمضاء</p>
                <img src="https://firebasestorage.googleapis.com/v0/b/yala-t7ady.appspot.com/o/bottom-images.png?alt=media&token=098aacbb-12ff-4909-8f8e-8dc8ea7d2fc3">
            </div>
        </div>
    </div>
</div>
</body>

</html>
