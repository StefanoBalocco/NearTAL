<?php
/*
 * Copyright (c) 2012, Stefano Balocco <Stefano.Balocco@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice, this
 *     list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *   * Redistributions in binary form must include, without any additional cost,
 *     the source code.
 *   * Neither the name of the copyright holders nor the names of its contributors
 *     may be used to endorse or promote products derived from this software
 *     without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( 'ganon.php' );

if( defined( 'NearTAL' ) && !defined( 'NearTAL_Compiler' ) )
{
	// Parse TALES path expressions
	function NearTAL_Compiler_ParseTALESPathExpression( &$compiledPath, $pathExpression, $attributes, $target )
	{
		$returnValue = array( false, false );
		$compiledPath = null;
		$paths = explode( '|', $pathExpression );
		$pathsCount = count( $paths );
		$parentheses = 0;
		$continue = true;
		for( $i = 0; $continue && ( $i < $pathsCount ); $i++ )
		{
			$path = array
			(
				'pieces' => explode( '/', $paths[ $i ] ),
				'keywordPosition' => 1
			);
			if( ( $path[ 'quantity' ] = count( $path[ 'pieces' ] ) ) > 0 )
			{
				// If the first piece isn't CONTEXT, I should check for variables.
				// But if is CONTEXTS, I should check for keywords in the array element 1 (if exists),
				// so the default value of keyword position is 1, and is adjusted if needed.
				if( 'CONTEXTS' != $path[ 'pieces' ][ 0 ] )
				{
					// First I need to check for variables, then I can check for keywords

					// The first variable type that I need to check is a defined variable
					$compiledPath .= 'NearTAL_NavigateTALESPath($localVariables[\'defined\'],\'' . trim( $paths[ $i ] ) . '\',0,' . $target . ')||';

					// Then for any variable given to the template engine
					$compiledPath .= 'NearTAL_NavigateTALESPath($variables,\'' . trim( $paths[ $i ] ) . '\',0,' . $target . ')' . ( ( $pathsCount > $i  ) ? '||' : null );

					// Is not CONTEXT, so I should check since the start
					$path[ 'keywordPosition' ]--;
				}
				if( array_key_exists( $path[ 'keywordPosition' ], $path[ 'pieces' ] ) )
				{
					switch( trim( $path[ 'pieces' ][ $path[ 'keywordPosition' ] ] ) )
					{
						case 'attrs':
						{
							if( ( 2 == ( $path[ 'quantity' ] - $path[ 'keywordPosition' ] ) ) && is_array( $attributes ) && array_key_exists( $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ], $attributes ) )
							{
								$compiledPath .= '(' . $target . '=array(' . ( isset( $attributes[ $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ] ] ) ? '\'' . $attributes[ $path[ 'pieces' ][ 1 + $path[ 'keywordPosition' ] ] ] . '\'' : 'null' ) . ',false))';
								// If attributes is in the path, and can be checked, I suppose that nothing else should be checked.
								$continue = false;
							}
							break;
						}
						case 'nothing':
						{
							if( is_null( $compiledPath ) )
							{
								$returnValue[ 1 ] = true;
							}
							$compiledPath .= '(' . $target . '=array(null,false))';
							// If "nothing" is in the path I suppose that nothing else should be checked.
							$continue = false;
							break;
						}
						case 'default':
						{
							if( is_null( $compiledPath ) )
							{
								$returnValue[ 0 ] = true;
							}
							$compiledPath .= '(' . $target . '=array(null,true))';
							// If default is in the path I suppose that nothing else should be checked.
							$continue = false;
							break;
						}
						case 'options':
						{
							if( $path[ 'quantity' ] > $path[ 'keywordPosition' ] )
							{
								// Actually is a mirror of a normal path, because nothing else can be passed to
								// the template engine.
								$compiledPath .= 'NearTAL_NavigateTALESPath($variables,\'' . $paths[ $i ] . '\',' . $path[ 'keywordPosition' ] + 1 . ',' . $target . ')' . ( ( $pathsCount > $i ) ? '||' : null );
							}
							break;
						}
						case 'repeat':
						{
							if( $path[ 'quantity' ] > $path[ 'keywordPosition' ] )
							{
								$parentheses++;
								$compiledPath .= '(';
								$variableName = '$localVariables[\'repeat\']';
								// I avoid the piece in the switch.
								for( $j = $path[ 'keywordPosition' ] + 1; $j < $path[ 'quantity' ]; $j++ )
								{
									$compiledPath .= 'array_key_exists(\'' . $path[ 'pieces' ][ $i ] . '\',' . $variableName . ')' . ( $path[ 'quantity' ] > ( $i + 1 ) ? '&&is_array(' . $variableName . '[\'' . $path[ 'pieces' ][ $i ] . '\'])&&' : '?array(' . $variableName . '[\'' . $path[ 'pieces' ][ $i ] . '\'],false):' );
									$variableName .= '[\'' . $path[ 'pieces' ][ $i ] . '\']';
								}
							}
						}
					}
				}
			}
		}
		if( $continue )
		{
			$compiledPath .= '('. $target . '=array(null,false))';
		}
		for( $i = 0; $i < $parentheses; $i++ )
		{
			$compiledPath .= ')';
		}
		return $returnValue;
	}

	// Parse TALES expressions
	function NearTAL_Compiler_ParseTALES( &$compiledExpression, $expression, $attributes, $target, $bool = false, $defaultIsFalse = true, $reverseBool = false )
	{
		$returnValue = array( false, false );
		$compiledExpression = null;
		// I need to remove any ' in the variable name, to avoid php injection
		// I could simply backslash it, but I prefer this way.
		$expression = str_replace( '\'', '&#039', $expression );
		$type = 'path';
		$value = $expression;
		if( false !== strpos( $expression, ':' ) )
		{
			list( $type, $value ) = explode( ':', $expression, 2 );
		}
		if( 'string' == $type )
		{
			preg_match_all( '/\$\{?([\w\/]+)\}?/', $value, $variables, PREG_OFFSET_CAPTURE );
			if( isset( $variables ) && ( false !== $variables ) && ( 2 == count( $variables ) ) && ( 0 < count( $variables[ 0 ] ) ) )
			{
				for( $i = count( $variables[ 0 ] ) - 1; 0 <= $i; $i-- )
				{
					$fastAnswers = NearTAL_Compiler_ParseTALESPathExpression( $compiledExpression, $variables[ 1 ][ $i ][ 0 ], $attributes, '$tmpVariable' );
					if( $fastAnswers[ 0 ] || $fastAnswers[ 1 ] )
					{
						$value = substr_replace( $value, null, $variables[ 0 ][ $i ][ 1 ], strlen( $variables[ 0 ][ $i ][ 0 ] ) );
					}
					else
					{
						$value = substr_replace( $value, '\'.((' . $compiledExpression . '&&!$tmpVariable[1])?$tmpVariable[0]:null).\'', $variables[ 0 ][ $i ][ 1 ], strlen( $variables[ 0 ][ $i ][ 0 ] ) );
					}
				}
			}
			if( 0 == strlen( $value ) )
			{
				$returnValue[ 1 ] = true;
			}
			$compiledExpression = '(' . $target . '=array(\'' . $value . '\',false))';
		}
		else
		{
			// Path is the default
			$returnValue = NearTAL_Compiler_ParseTALESPathExpression( $compiledExpression, $value, $attributes, $target );
			if( 'not' == $type )
			{
				$bool = true;
				$reverseBool = true;
			}
		}
		// "Not" is evalutated here.
		$compiledExpression = ( ( $bool ? '(' : null ) . $compiledExpression . ( $bool ? ')&&' . ( $reverseBool ? '!' : null ) . sprintf( '(' . ( $defaultIsFalse ? '!' : null ) . '%1$s[1]' . ( $defaultIsFalse ? '&&' : '||(' ) . '!is_null(%1$s[0])&&((is_bool(%1$s[0])&&%1$s[0])||(is_string(%1$s[0])&&(0<strlen(%1$s[0]))||(is_numeric(%1$s[0])&&(0<%1$s[0]))))' . ( $defaultIsFalse ? null : ')' ) . ')', $target ) : null ) );
		return $returnValue;
	}

	function NearTAL_Compiler_ParseMETAL( &$returnValue, &$compilerData, &$parser, $removeMacros = false )
	{
		$macros = $parser->select( '[data-metal-define-macro]' );
		foreach( $macros as $macro )
		{
			$macroName = $macro->getAttribute( 'data-metal-define-macro' );
			if( array_key_exists( $macroName, $returnValue ) )
			{
				NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_DuplicatedMacro' ), $macroName );
			}
			$macro->deleteAttribute( 'data-metal-define-macro' );
			$compilerData[ 'metal' ][ $macroName ] = $macro->getOuterText( );
			if( $removeMacros )
			{
				$macro->delete( );
			}
		}
		return $returnValue;
	}

	function NearTAL_Compiler( &$returnValue, $template, $page, $variables = null, $directories = null )
	{
		$compilerData = array
		(
			'metal' => array
			(
			),
			'keywords' => array
			(
				'define',
				'condition',
				'repeat',
				'replace',
				'content',
				'omit-tag',
				'attributes'
			)
		);
		if( file_exists( $directories[ 'templates' ] ) && is_dir( $directories[ 'templates' ] ) && file_exists( $directories[ 'templates' ] . '/' . $template ) && is_dir( $directories[ 'templates' ] . '/' . $template ) && ( file_exists( $directories[ 'temp' ] ) || mkdir( $directories[ 'temp' ], 0700 ) ) && is_dir( $directories[ 'temp' ] ) )
		{
			if( file_exists( $directories[ 'templates' ] . '/' . $template . '.metal.html' ) && ( false !== ( $content = file_get_contents( $directories[ 'templates' ] . '/' . $template . '.metal.html' ) ) ) )
			{
				$parser = str_get_dom( $content );
				NearTAL_Compiler_ParseMETAL( $returnValue, $compilerData, $parser, false );
				unset( $parser );
			}
			if( file_exists( $directories[ 'templates' ] . '/' . $template . '/' . $page . '.html' ) && ( false !== ( $content = file_get_contents( $directories[ 'templates' ] . '/' . $template . '/' . $page . '.html' ) ) ) )
			{
				$parser = str_get_dom( $content );
				NearTAL_Compiler_ParseMETAL( $returnValue, $compilerData, $parser, true );
				$nodes = $parser->select( '[data-metal-use-macro]' );
				foreach( $nodes as $node )
				{
					$macroName = $node->getAttribute( 'data-metal-use-macro' );
					if( array_key_exists( $macroName, $compilerData[ 'metal' ] ) )
					{
						$availableSlots = array( );
						$slots = $node( '[data-metal-fill-slot]' );
						foreach( $slots as $slot )
						{
							$slotName = $slots->getAttribute( 'data-metal-fill-slot' );
							if( array_key_exists( $slotName, $slots ) )
							{
								NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_DuplicatedSlot' ), $slotName );
							}
							$slot->deleteAttribute( 'data-metal-fill-slot' );
							$availableSlots[ $slotName ] = $slot->getOuterText( );
						}
						$node->setOuterText( $compilerData[ 'metal' ][ $macroName ] );
						$slots = $node( '[data-metal-define-slot]' );
						foreach( $slots as $slot )
						{
							$slotName = $slots->getAttribute( 'data-metal-define-slot' );
							if( array_key_exists( $slotName, $availableSlots ) )
							{
								$slot->setOuterText( $availableSlots[ $slotName ] );
							}
							else
							{
								$slot->deleteAttribute( 'data-metal-define-slot' );
								NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_UnknownSlot' ), $slotName );
							}
						}
						unset( $availableSlots );
					}
					else
					{
						NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_UnknownMacro' ), $macroName );
					}
				}
				$output = '<?php if(defined(\'NearTAL\')){$localVariables=array(\'defined\'=>array(),\'repeat\'=>array(),\'stack\'=>array(\'defined\'=>array(),\'repeat\'=>array()),\'template\'=>array()); ?>';
				$localVariablesIndex = 0;
				$count = 0;
				$selectFilter = array( );
				foreach( $compilerData[ 'keywords' ] as $keyword )
				{
					$selectFilter[ ] = '[data-tal-' . $keyword . ']';
				}
				$nodes = $parser->select( implode( ',', $selectFilter ) );
				$localVariablesIndex = 0;
				foreach( $nodes as $node )
				{
					$changes = array
					(
						'attributes' => array( ),
						'outside' => array
						(
							'pre' => null,
							'post' => null
						),
						'inside' => array
						(
							'pre' => null,
							'post' => null
						)
					);
					foreach( $compilerData[ 'keywords' ] as $keyword )
					{
						$attribute = $node->getAttribute( 'data-tal-' . $keyword );
						if( !is_null( $attribute ) )
						{
							if( !is_array( $attribute ) && !empty( $attribute ) )
							{
								switch( $keyword )
								{
									case 'define':
									{
										$tmpVariable = array( );
										$tmpVariable[ 'count' ] = preg_match_all( '/[\s]*(?:(?:(local|world)[\s]+)?(.+?)[\s]+(.+?)[\s]*(?:(?<!;);(?!;)|$))+?/', $attribute, $tmpVariable[ 'elements' ], PREG_SET_ORDER );
										for( $j = 0; $j < $tmpVariable[ 'count' ]; $j++ )
										{
											$tmpVariable[ 'name' ] = str_replace( '\'', '&#039', $tmpVariable[ 'elements' ][ $j ][ 2 ] );
											$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $tmpVariable[ 'elements' ][ $j ][ 3 ], $node->attributes, '$tmpVariable' );
											$changes[ 'outside' ][ 'pre' ] .= '$localVariables[\'template\'][' . $localVariablesIndex . ']=(' . $tmpVariable[ 'compiledExpression' ] . '&&!$tmpVariable[1]);if($localVariables[\'template\'][' . $localVariablesIndex++ . ']){NearTAL_LocalVariablesPush($localVariables,\'defined\',\'' . $tmpVariable[ 'name' ] . '\',$tmpVariable[0]);}unset($tmpVariable);';
											if( 'world' != $tmpVariable[ 'elements' ][ $j ][ 1 ] )
											{
												$changes[ 'outside' ][ 'post' ] = 'if($localVariables[\'template\'][' . ( $localVariablesIndex - 1 ) . ']){NearTAL_LocalVariablesPop($localVariables,\'defined\',\'' . $tmpVariable[ 'name' ] . '\');unset($localVariables[\'template\'][' . ( $localVariablesIndex - 1 ) . ']);}' . $changes[ 'outside' ][ 'post' ];
											}
										}
										unset( $tmpVariable );
										break;
									}
									case 'condition':
									{
										$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $attribute, $node->attributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']', true, true );
										if( $fastAnswers[ 0 ] || $fastAnswers[ 1 ] )
										{
											$node->delete( );
										}
										else
										{
											$changes[ 'outside' ][ 'pre' ] .= 'if(' . $tmpVariable[ 'compiledExpression' ] . '){';
											$changes[ 'outside' ][ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);' . $changes[ 'outside' ][ 'post' ];
										}
										break;
									}
									case 'repeat':
									{
										$tmpVariable[ 'elements' ] = explode( " ", $attribute, 2 );
										$tmpVariable[ 'name' ] = str_replace( '\'', '&#039', $tmpVariable[ 'elements' ][ 0 ] );
										$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $tmpVariable[ 'elements' ][ 1 ], $node->attributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']' );
										$changes[ 'outside' ][ 'pre' ] .= sprintf( 'if((%1$s)&&($localVariables[\'template\'][%2$d][1]||is_array($localVariables[\'template\'][%2$d][0]))){$localVariables[\'template\'][%3$d]=array(false,false);if(!$localVariables[\'template\'][%2$d][1]){($localVariables[\'template\'][%3$d][0]=true)&&NearTAL_LocalVariablesPush($localVariables,\'repeat\',\'%4$s\',null);($localVariables[\'template\'][%3$d][1]=true)&&NearTAL_LocalVariablesPush($localVariables,\'defined\',\'%4$s\',null);$localVariables[\'repeat\'][\'%4$s\'][\'index\']=-1;$localVariables[\'repeat\'][\'%4$s\'][\'length\']=count($localVariables[\'template\'][%2$d][0]);}do{if(!$localVariables[\'template\'][%2$d][1]){$localVariables[\'defined\'][\'%4$s\']=array_shift($localVariables[\'template\'][%2$d][0]);$localVariables[\'repeat\'][\'%4$s\'][\'index\']++;$localVariables[\'repeat\'][\'%4$s\'][\'number\']=$localVariables[\'repeat\'][\'%4$s\'][\'index\']+1;$localVariables[\'repeat\'][\'%4$s\'][\'even\']=($localVariables[\'repeat\'][\'%4$s\'][\'number\']%%2?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'odd\']=!$localVariables[\'repeat\'][\'%4$s\'][\'even\'];$localVariables[\'repeat\'][\'%4$s\'][\'start\']=($localVariables[\'repeat\'][\'%4$s\'][\'index\']?false:true);$localVariables[\'repeat\'][\'%4$s\'][\'end\']=(($localVariables[\'repeat\'][\'%4$s\'][\'number\']==$localVariables[\'repeat\'][\'%4$s\'][\'length\'])?true:false);$localVariables[\'repeat\'][\'%4$s\'][\'letter\']=NearTAL_NumberToLetter($localVariables[\'repeat\'][\'%4$s\'][\'index\']);$localVariables[\'repeat\'][\'%4$s\'][\'Letter\']=strtoupper($localVariables[\'repeat\'][\'%4$s\'][\'letter\']);}', $tmpVariable[ 'compiledExpression' ], $localVariablesIndex++, $localVariablesIndex, $tmpVariable[ 'name' ] );
										$changes[ 'outside' ][ 'post' ] = sprintf( '}while(!$localVariables[\'template\'][%1$d][1]&&!empty($localVariables[\'template\'][%1$d][0]));$localVariables[\'template\'][%2$d][0]&&NearTAL_LocalVariablesPop($localVariables,\'repeat\',\'%3$s\');$localVariables[\'template\'][%2$d][1]&&NearTAL_LocalVariablesPop($localVariables,\'defined\',\'%3$s\');unset($localVariables[\'template\'][%1$d],$localVariables[\'template\'][%2$d]);}', $localVariablesIndex - 1, $localVariablesIndex++, $tmpVariable[ 'name' ] ) . $changes[ 'outside' ][ 'post' ];
										break;
									}
									case 'replace':
									case 'content':
									{
										if( ( 'content' == $keyword ) || ( ( 'replace' == $keyword ) && !array_key_exists( 'data-tal-content', $node->attributes ) ) )
										{
											$tmpVariable = array( );
											if( ( 'structure ' == substr( $attribute, 0, 10 ) ) || ( 'text ' == substr( $attribute, 0, 5 ) ) )
											{
												$tmpVariable[ 'parameters' ] = explode( " ", $attribute, 2 );
											}
											else
											{
												$tmpVariable[ 'parameters' ] = array( "text", $attribute );
											}
											$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $tmpVariable[ 'parameters' ][ 1 ], $node->attributes, '$localVariables[\'template\'][' . $localVariablesIndex . ']' );
											if( !$fastAnswers[ 0 ] )
											{
												if( !$fastAnswers[ 1 ] )
												{
													$tmpVariable[ 'pre' ] =  'if(' . $tmpVariable[ 'compiledExpression' ] . '&&!$localVariables[\'template\'][' . $localVariablesIndex . '][1]&&!is_null($localVariables[\'template\'][' . $localVariablesIndex . '][0])){echo(' . ( 'text' == $tmpVariable[ 'parameters' ][0] ? 'str_replace(array(\'&\',\'<\',\'>\'),array(\'&amp\',\'&lt\',\'&gt\'),' : null ) . '(is_bool($localVariables[\'template\'][' . $localVariablesIndex . '][0])?($localVariables[\'template\'][' . $localVariablesIndex . '][0]?1:0):$localVariables[\'template\'][' . $localVariablesIndex . '][0])'. ( 'text' == $tmpVariable[ 'parameters' ][0] ? ')' : null ). ');}elseif($localVariables[\'template\'][' . $localVariablesIndex . '][1]){';
													$tmpVariable[ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);';
													if( 'content' == $keyword )
													{
														$changes[ 'inside' ][ 'pre' ] = $tmpVariable[ 'pre' ] . $changes[ 'inside' ][ 'pre' ];
														$changes[ 'inside' ][ 'post' ] .= $tmpVariable[ 'post' ];
													}
													else
													{
														$changes[ 'outside' ][ 'pre' ] .= $tmpVariable[ 'pre' ];
														$changes[ 'outside' ][ 'post' ] = $tmpVariable[ 'post' ] . $changes[ 'outside' ][ 'post' ];
													}
												}
												else
												{
													if( 'content' == $keyword )
													{
														foreach( $node->children as $id => $child )
														{
															$node->deleteChild( $id );
														}
													}
													else
													{
														$node->delete( );
													}
												}
											}
										}
										break;
									}
									case 'attributes':
									{
										$tmpVariable = array( );
										$tmpVariable[ 'count' ] = preg_match_all( '/(?:[\s]*(.+?)[\s]+(.+?)[\s]*(?:(?<!;);(?!;)|$))+?/', $attribute, $tmpVariable[ 'elements' ], PREG_SET_ORDER );
										for( $j = 0; $j < $tmpVariable[ 'count' ]; $j++ )
										{
											$changes[ 'attributes' ][ $tmpVariable[ 'elements' ][ $j ][ 1 ] ] = $tmpVariable[ 'elements' ][ $j ][ 2 ];
										}
										break;
									}
									case 'omit-tag':
									{
										$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $attribute, $node->attributes, '$tmpVariable', true );
										if( !$fastAnswers[ 0 ] && !$fastAnswers[ 1 ] )
										{
											$changes[ 'outside' ][ 'pre' ] .= '$localVariables[\'template\'][' . $localVariablesIndex . ']=!(' . $tmpVariable[ 'compiledExpression' ] . ');unset($tmpVariable);if($localVariables[\'template\'][' . $localVariablesIndex . ']){';
											// If isn't selfclosed OR if I already need to put some code between starting and closing tag, I put some data "inside"
											// TODO: a regexp to remove ? >< ?php and {}
											if( !$node->self_close || isset( $changes[ 'inside' ][ 'pre' ] ) || isset( $changes[ 'inside' ][ 'post' ] ) || 0 < count( $changes[ 'attributes' ] ) )
											{
												$changes[ 'inside' ][ 'pre' ] = '}' . $changes[ 'inside' ][ 'pre' ];
												$changes[ 'inside' ][ 'post' ] .= 'if($localVariables[\'template\'][' . $localVariablesIndex . ']){';
											}
											$changes[ 'outside' ][ 'post' ] = '}unset($localVariables[\'template\'][' . $localVariablesIndex++ . ']);' . $changes[ 'outside' ][ 'post' ];
										}
										break;
									}
								}
							}
							$node->deleteAttribute( 'data-tal-' . $keyword );
						}
					}
					if( !empty( $changes[ 'attributes' ] ) )
					{
						foreach ( $changes[ 'attributes' ] as $name => $value )
						{
							$fastAnswers = NearTAL_Compiler_ParseTALES( $tmpVariable[ 'compiledExpression' ], $value, $node->attributes, '$tmpVariable' ) ;
							if( !$fastAnswers[ 0 ] )
							{
								if( !$fastAnswers[ 1 ] )
								{
									$tmpVariable[ 'exists' ] = array_key_exists( $name, $node->attributes );
									$tmpVariable[ 'attribute' ] = '<?php if(' . $tmpVariable[ 'compiledExpression' ] . ( !$tmpVariable[ 'exists' ] ? '&&!$tmpVariable[1]' : null ) . '){?>' . $name . '="<?php echo('. ( $tmpVariable[ 'exists' ] ? '$tmpVariable[1]?\'' . str_replace( '\'', '&#039', $node->attributes[ $name ] ) . '\':' : null ) . '(is_bool($tmpVariable[0])?($tmpVariable[0]?1:0):$tmpVariable[0]));?>"<?php }unset($tmpVariable);?>';
									$node->attributes[ $tmpVariable[ 'attribute' ] ] = $tmpVariable[ 'attribute' ];
									if( $tmpVariable[ 'exists' ] )
									{
										unset( $node->attributes[ $name ] );
									}
								}
								else
								{
									$node->attributes[ $name ] = null;
								}
							}
						}
					}
					unset( $tmpVariable );
					if( !is_null( $changes[ 'outside' ][ 'pre'] ) )
					{
						$position = $node->index( true );
						$node->parent->addXML
						(
							'php',
							' ' . $changes[ 'outside' ][ 'pre' ],
							array( ),
							$position
						);
					}
					if( !is_null( $changes[ 'outside' ][ 'post'] ) )
					{
						$position = $node->index( true ) + 1;
						$node->parent->addXML
						(
							'php',
							' ' . $changes[ 'outside' ][ 'post' ],
							array( ),
							$position
						);
					}
					if( !is_null( $changes[ 'inside' ][ 'pre'] ) )
					{
						$position = 0;
						$node->addXML
						(
							'php',
							' ' . $changes[ 'inside' ][ 'pre' ],
							array( ),
							$position
						);
					}
					if( !is_null( $changes[ 'inside' ][ 'post'] ) )
					{
						$position = -1;
						$node->addXML
						(
							'php',
							' ' . $changes[ 'inside' ][ 'post' ],
							array( ),
							$position
						);
					}
				}
				$output .= str_replace( "?>\n", "?>\n\n", str_replace( "\r", '', $parser ) ) . '<?php }?>';
				$file = null;
				if( ( false !== ( $temporaryFilename = tempnam( $directories[ 'temp' ], 'TAL' ) ) ) && ( false !== ( $file = fopen( $temporaryFilename, 'wb' ) ) ) )
				{
					fwrite( $file, $output );
					fclose( $file );
					if( ( file_exists( $directories[ 'cache' ] ) || mkdir( $directories[ 'cache' ], 0700 ) ) && is_dir( $directories[ 'cache' ] ) && ( file_exists( $directories[ 'cache' ] . '/' . $template ) || mkdir( $directories[ 'cache' ] . '/' . $template, 0700 ) ) && is_dir( $directories[ 'cache' ] . '/' . $template ) )
					{
						if( !rename( $temporaryFilename, $directories[ 'cache' ] . '/' . $template . '/' . $page . '.php' ) )
						{
							NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_Rename' ), $temporaryFilename );
						}
					}
					else
					{
						NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_Cache' ), null );
					}
				}
				else
				{
					NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_TemporaryFile' ), null );
				}
			}
			else
			{
				NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_Template' ), null );
			}
		}
		else
		{
			NearTAL_AddError( $returnValue, constant( 'NearTAL_Error_Path' ), null );
		}
	}
	define( 'NearTAL_Compiler', '1.0' );
}
?>