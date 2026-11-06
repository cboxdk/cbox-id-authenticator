// Language-neutral creative assets: exact app mark, no generated UI or claims.
// swift resources/store/tools/brand-art.swift <output-dir> <app-icon>
import AppKit
import Foundation
let out=CommandLine.arguments[1],icon=NSImage(contentsOfFile:CommandLine.arguments[2])!
func c(_ h:UInt32)->NSColor{NSColor(srgbRed:CGFloat((h>>16)&255)/255,green:CGFloat((h>>8)&255)/255,blue:CGFloat(h&255)/255,alpha:1)}
for (name,w,h) in [("header",3840,1646),("search",1920,1280)] {
 let rep=NSBitmapImageRep(bitmapDataPlanes:nil,pixelsWide:w,pixelsHigh:h,bitsPerSample:8,samplesPerPixel:4,hasAlpha:true,isPlanar:false,colorSpaceName:.deviceRGB,bytesPerRow:0,bitsPerPixel:0)!
 NSGraphicsContext.saveGraphicsState();NSGraphicsContext.current=NSGraphicsContext(bitmapImageRep:rep)
 let W=CGFloat(w),H=CGFloat(h),center=NSPoint(x:W/2,y:H/2),size=H*0.56
 c(0xF7F5F0).setFill();NSRect(x:0,y:0,width:W,height:H).fill()
 c(0xE9EEF4).setFill();NSBezierPath(ovalIn:NSRect(x:-W*0.2,y:-H*1.7,width:W*1.4,height:H*2.35)).fill()
 c(0x0850AB).setFill();NSBezierPath(ovalIn:NSRect(x:-W*0.25,y:-H*1.85,width:W*1.5,height:H*2.2)).fill()
 for scale:CGFloat in [1.35,1.7,2.05] {
  let r=size*scale
  let circle=NSBezierPath(ovalIn:NSRect(x:center.x-r/2,y:center.y-r/2,width:r,height:r));circle.lineWidth=H*0.0018;c(0xD9E2EB).setStroke();circle.stroke()
 }
 let rect=NSRect(x:center.x-size/2,y:center.y-size/2,width:size,height:size)
 NSGraphicsContext.saveGraphicsState();let shadow=NSShadow();shadow.shadowColor=NSColor.black.withAlphaComponent(0.18);shadow.shadowBlurRadius=H*0.055;shadow.shadowOffset=NSSize(width:0,height:-H*0.024);shadow.set();c(0x0850AB).setFill();NSBezierPath(roundedRect:rect,xRadius:size*0.224,yRadius:size*0.224).fill();NSGraphicsContext.restoreGraphicsState()
 NSGraphicsContext.saveGraphicsState();NSBezierPath(roundedRect:rect,xRadius:size*0.224,yRadius:size*0.224).addClip();icon.draw(in:rect);NSGraphicsContext.restoreGraphicsState()
 NSGraphicsContext.restoreGraphicsState()
 let jpeg = rep.representation(using: .jpeg, properties: [.compressionFactor: 0.98])!
 if name == "header" {
  // App Store Connect rejects .jpg for headers despite listing it in the docs.
  // Decode the opaque JPEG before PNG export to remove the alpha channel.
  let opaque = NSBitmapImageRep(data: jpeg)!
  try opaque.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: "\(out)/\(name).png"))
 } else {
  try jpeg.write(to: URL(fileURLWithPath: "\(out)/\(name).jpg"))
 }
 print("\(name): \(w)×\(h)")
}
