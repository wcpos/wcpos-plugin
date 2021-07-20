const path = require("path");
// const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
// const ESLintPlugin = require('eslint-webpack-plugin');
// import path from 'path';
// import ForkTsCheckerWebpackPlugin from 'fork-ts-checker-webpack-plugin';
// import ESLintPlugin from 'eslint-webpack-plugin';

// const NODE_ENV = process.env.NODE_ENV || 'development';

module.exports = {
  entry: {
    settings: './src/settings.tsx',
	},
	output: {
		path: path.resolve(__dirname, "admin-client"),
		filename: "assets/js/[name].js",
		publicPath: "/"
	},
	externals: {
		react: 'React',
		"react-dom": 'ReactDOM',
		wp: 'wp',
		'@wordpress/components': 'wp.components'
	},
	module: {
		rules: [
			{
				test: /\.(ts|js)x?$/i,
				exclude: /node_modules/,
				use: {
					loader: "babel-loader",
					options: {
						presets: [
							"@babel/preset-env",
							"@babel/preset-react",
							"@babel/preset-typescript",
						],
					},
				},
			},
		],
	},
	resolve: {
		extensions: [".tsx", ".ts", ".js"],
	},
	plugins: [
		// new ForkTsCheckerWebpackPlugin({
		// 	async: false
		// }),
		// new ESLintPlugin({
		// 	extensions: ["js", "jsx", "ts", "tsx"],
		// }),
	],
};
