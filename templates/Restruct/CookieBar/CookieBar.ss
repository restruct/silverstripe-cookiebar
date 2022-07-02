<% with $SiteConfig %>
<script type="text/x-template" id="cookiebar-template" data-legacyid="cookiebarholder">
	<div id="cookiebar">
		<div class="cookiebar-container container">
		    <div class="cookiebar-row row pt-2 pb-2">
                <div class="cookiebar-notification col-md col-lg-7 offset-lg-1">
                    <div class="notification-inner typography">
                        <% if $CookieImage %>
                            $CookieImage.SetHeight(80)
                        <% end_if %>
                        <div class="notification-title">
                            $CookieBarTitle
                        </div>
                        <div class="notification-content">
                            $CookieBarContent
                        </div>
                    </div>
                </div>
                <div class="cookiebar-links col-md-4 col-lg-3">
                    <a class="acceptlink btn btn-success" data-purpose="acceptcookies" id="acceptcookies" href="$Top.AcceptCookiesLink">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg" viewBox="0 0 16 16">
                          <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                        </svg>
                        {$CookieCloseText}
                    </a>
                    <% if $CookiePage %>
                        <a class="infolink btn btn-link" href="$CookiePage.Link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
                              <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                              <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                            </svg>
                            {$CookieMoreText}
                        </a>
                    <% end_if %>
                </div>
            </div>
		</div>
	</div>
</script>
<% end_with %>

