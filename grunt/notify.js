module.exports = {
	/**
	 * grunt-notify
	 *
	 * Automatic desktop notifications for Grunt errors and warnings using
	 * Growl for OS X or Windows, Mountain Lion and Mavericks Notification
	 * Center, and Notify-Send.
	 *
	 * @link https://www.npmjs.com/package/grunt-notify
	 */
	options: {
		title: 'Grunt: <%= package.title %>'
	},
	default: {
		options: {
			message: 'All tasks have completed with no errors.'
		}
	},
	styles: {
		options: {
			message: 'CSS is compiled.'
		}
	},
	scripts: {
		options: {
			message: 'JS is all good.'
		}
	},
	build: {
		options: {
			message: 'Theme has been built.'
		}
	}
};
