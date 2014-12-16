module.exports = function(grunt) {
  require('jit-grunt')(grunt);
  
	grunt.initConfig({
		less: {
      development: {
        options: {
          compress: true,
          yuicompress: true,
          optimization: 2
        },
        files: {
          "./public/mobile-ci/stylesheet/main.css": "./public/mobile-ci/styles-less/main.less"
        }
      }
		},
		watch: {
			styles: {
				options: {
          nospawn: true
					// spawn: false,
					// event: ["added", "deleted", "changed"]
				},
				files: ["./public/mobile-ci/styles-less/**.*.less", "./public/mobile-ci/stylesheet/**.*.css"],
				tasks: ["less"]
			}
		}
	});

	grunt.loadNpmTasks("grunt-contrib-less");
	grunt.loadNpmTasks("grunt-contrib-watch");

	grunt.registerTask("default", ["less"]);
};
