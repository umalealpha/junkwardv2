<!--begin::Aside-->
<div class="kt-aside kt-aside--fixed kt-grid__item kt-grid kt-grid--desktop kt-grid--hor-desktop" id="kt_aside">
    <!--begin::Brand-->
    <div class="kt-aside__brand kt-grid__item " id="kt_aside_brand">
        <div class="kt-aside__brand-logo">
            <a>
                <img alt="Logo" src="{{ asset('images/logo.png') }}" />
            </a>
        </div>
        <div class="kt-aside__brand-tools">
            <button class="kt-aside__brand-aside-toggler kt-aside__brand-aside-toggler--left" id="kt_aside_toggler"><span></span></button>
        </div>
    </div>
    <!--end::Brand-->

    <!--begin::Aside Menu-->
    <div class="kt-aside-menu-wrapper kt-grid__item kt-grid__item--fluid" id="kt_aside_menu_wrapper">
        <div id="kt_aside_menu" class="kt-aside-menu " data-ktmenu-vertical="1" data-ktmenu-drop="1" data-ktmenu-scroll="1">
            <ul class="kt-menu__nav ">
                <!--begin::1 Level-->
                <li class="kt-menu__item kt-menu__item--active" aria-haspopup="true">
                    <a href="{{ route('hr.ad-group-policy') }}" class="kt-menu__link">
                        <i class="kt-menu__link-icon flaticon2-copy"></i>
                        <span class="kt-menu__link-text">AD Group Policy</span>
                    </a>
                </li>
                <!--end::1 Level-->

                <!--begin::1 Level-->
                <li class="kt-menu__item" aria-haspopup="true">
                    <a href="{{ route('hr.logout') }}" class="kt-menu__link" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="kt-menu__link-icon flaticon2-logout"></i>
                        <span class="kt-menu__link-text">Logout</span>
                    </a>
                    <form id="logout-form" action="{{ route('hr.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
                <!--end::1 Level-->
            </ul>
        </div>
    </div>
    <!--end::Aside Menu-->
</div>
<!--end::Aside-->
