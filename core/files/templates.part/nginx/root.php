<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

set $root_path {% $VIRTUAL_DOCROOT %};

{% if $PLATFORM == default %}

	{% if $AUTOSUBDOMAIN != off and $AUTOSUBDOMAIN != "" %}

		{% if $AUTOSUBDOMAIN == autosubdomain_subdir %}

			set $subdomain "";

		{% elif $AUTOSUBDOMAIN == autosubdomain_dir %}

			set $subdomain {% $AUTOSUBDOMAIN_SUBDOMAIN_PART %};

		{% endif %}

		if ($host ~* ^((.*).{% $NAME %})$) {

			{% if $AUTOSUBDOMAIN == autosubdomain_dir %}

				set $subdomain $1;

			{% elif $AUTOSUBDOMAIN == autosubdomain_subdir %}

				set $subdomain $2;

			{% endif %}

		}
		root $root_path/$subdomain;

	{% else %}

		root $root_path;

	{% endif %}

{% else %}

	root $root_path;

{% endif %}
